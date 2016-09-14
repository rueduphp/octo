<?php
    namespace Octo;

    class S3
    {
        protected $bucket, $s3Client = null, $lastRemoteFile = null;

        protected static $instance = null;

        function __construct($accessKeyId = null, $secretAccessKey = null, $bucket = null)
        {
            $accessKeyId     = is_null($accessKeyId)     ? optGet('s3.key',     Config::get('s3.key'))                                 : $accessKeyId;
            $secretAccessKey = is_null($secretAccessKey)  ? optGet('s3.secret', Config::get('s3.secret'))                              : $secretAccessKey;
            $bucket          = is_null($bucket)          ? optGet('s3.bucket',  Config::get('s3.bucket', SITE_NAME . '_buxket'))       : $bucket;

            $this->bucket   = $bucket;

            $this->s3Client = \Aws\S3\S3Client::factory([
                'key'       => $accessKeyId,
                'secret'    => $secretAccessKey,
                'signature' => Config::get('s3.signature', 'v4'),
                'region'    => Config::get('s3.region', 'eu-west-1'),
                'version'   => Config::get('s3.version', 'latest')
            ]);

            self::$instance = $this;
        }

        public static function getInstance()
        {
            if (!self::$instance) {
                new S3(
                    optGet('s3.key',       Config::get('s3.key')),
                    optGet('s3.secret',    Config::get('s3.secret')),
                    optGet('s3.bucket',    Config::get('s3.bucket', SITE_NAME . '_buxket'))
                );
            }

            return self::$instance;
        }

        function get($remoteFile)
        {
            $localFile              = $this->getLocalFile($remoteFile);
            $this->lastRemoteFile   = $remoteFile;
            $download               = false;

            if (!file_exists($localFile)) {
                $download = true;
            } else {
                $iterator       = $this->s3Client->getIterator('ListObjects', [
                    'Bucket'    => $this->bucket,
                    'Prefix'    => $remoteFile,
                    'Delimiter' => '/',
                ]);

                foreach ($iterator as $object) {
                    $remoteDate = date("U", strtotime($object['LastModified']));
                    $localDate  = filemtime($localFile);

                    if ($remoteDate > $localDate) {
                        $download = true;
                    }

                    break;
                }
            }

            if ($download) {
                try {
                    $result = $this->s3Client->getObject([
                        'Bucket'    => $this->bucket,
                        'Key'       => $remoteFile,
                    ]);
                } catch (\Exception $e) {
                    error_log("Error recovering $remoteFile from S3: " . $e->getMessage());

                    return null;
                }

                File::put($localFile, $result['Body']);
                touch($localFile, strtotime($result['LastModified']));
            }

            return $localFile;
        }

        function save($remoteFile, $content)
        {
            $this->lastRemoteFile = $remoteFile;
            $this->s3Client->upload($this->bucket, $remoteFile, $content);
        }

        public function getLast()
        {
            return $this->lastRemoteFile;
        }

        public function getList($path = "")
        {
            $files = [];

            $options = [
                'Bucket' => $this->bucket,
            ];

            if ($path){
                $options['Prefix'] = $path;
                $options['Delimiter'] = '/';
            }

            $iterator = $this->s3Client->getIterator('ListObjects', $options);

            foreach ($iterator as $object) {
                $files[] = [
                    'timestamp' => date("U", strtotime($object['LastModified'])),
                    'filename'  => $object['Key'],
                ];
            }

            return $files;
        }

        public function listBuckets()
        {
            $buckets = $this->s3Client->listBuckets();

            return $buckets["Buckets"];
        }

        public function delete($remoteFile)
        {
            File::delete($this->getLocalFile($remoteFile));

            $this->s3Client->deleteObject([
                'Bucket'    => $this->bucket,
                'Key'       => $remoteFile,
            ]);
        }

        private function getLocalFile($file)
        {
            $key = sha1($this->bucket . $file);
            $dir = Config::get('s3.cache', session_save_path());

            return $dir . DS . $key;
        }
    }

