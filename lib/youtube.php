<?php
    namespace Octo;

    class Youtube
    {
        const ORDER_DATE            = 'date';
        const ORDER_RATING          = 'rating';
        const ORDER_RELEVANCE       = 'relevance';
        const ORDER_TITLE           = 'title';
        const ORDER_VIDEOCOUNT      = 'videoCount';
        const ORDER_VIEWCOUNT       = 'viewCount';

        //eventType
        const EVENT_TYPE_LIVE       = 'live';
        const EVENT_TYPE_COMPLETED  = 'completed';
        const EVENT_TYPE_UPCOMING   = 'upcoming';

        //type in search api
        const SEARCH_TYPE_CHANNEL   = 'channel';
        const SEARCH_TYPE_PLAYLIST  = 'playlist';
        const SEARCH_TYPE_VIDEO     = 'video';

        protected $youtube_key;
        protected $referer;

        protected $APIs = array(
            'videos.list'           => 'https://www.googleapis.com/youtube/v3/videos',
            'search.list'           => 'https://www.googleapis.com/youtube/v3/search',
            'channels.list'         => 'https://www.googleapis.com/youtube/v3/channels',
            'playlists.list'        => 'https://www.googleapis.com/youtube/v3/playlists',
            'playlistItems.list'    => 'https://www.googleapis.com/youtube/v3/playlistItems',
            'activities'            => 'https://www.googleapis.com/youtube/v3/activities',
        );

        public $page_info = [];

        public function __construct($params = [])
        {
            if (!is_array($params)) {
                throw new \InvalidArgumentException('The configuration options must be an array.');
            }

            if (!array_key_exists('key', $params)) {
                $params['key'] = Config::get('youtube.key');
            }

            if (empty($params['key'])) {
                throw new \InvalidArgumentException('Google API key is required, please visit http://code.google.com/apis/console');
            }

            $this->setApiKey($params['key']);

            $referer = isAke($params, 'referer', Config::get('youtube.referer'));

            if ($referer) {
                $this->setReferer($referer);
            }
        }

        public function setApiKey($apiKey)
        {
            $this->youtube_key = $apiKey;
        }

        public function setReferer($referer)
        {
            $this->referer = $referer;
        }

        public function getVideoInfo($vId)
        {
            $urlApi = $this->get('videos.list');

            $params = array(
                'id'    => $vId,
                'part'  => 'id, snippet, contentDetails, player, statistics, status'
            );

            $apiData = $this->call($urlApi, $params);

            return $this->getSingle($apiData);
        }

        public function getVideosInfo($vIds)
        {
            $ids    = is_array($vIds) ? implode(',', $vIds) : $vIds;
            $urlApi = $this->get('videos.list');

            $params = array(
                'id'    => $ids,
                'part'  => 'id, snippet, contentDetails, player, statistics, status'
            );

            $apiData = $this->call($urlApi, $params);

            return $this->makeList($apiData);
        }

        public function search($q, $maxResults = 10)
        {
            $params = array(
                'q'             => $q,
                'part'          => 'id, snippet',
                'maxResults'    => $maxResults
            );

            return $this->research($params);
        }

        public function searchVideos($q, $maxResults = 10, $order = null)
        {
            $params = array(
                'q'             => $q,
                'type'          => 'video',
                'part'          => 'id, snippet',
                'maxResults'    => $maxResults
            );

            if (!empty($order)) {
                $params['order'] = $order;
            }

            return $this->research($params);
        }

        public function searchChannelVideos($q, $channelId, $maxResults = 10, $order = null)
        {
            $params = array(
                'q'             => $q,
                'type'          => 'video',
                'channelId'     => $channelId,
                'part'          => 'id, snippet',
                'maxResults'    => $maxResults
            );

            if (!empty($order)) {
                $params['order'] = $order;
            }

            return $this->research($params);
        }


        public function searchChannelLiveStream($q, $channelId, $maxResults = 10, $order = null)
        {
            $params = array(
                'q'          => $q,
                'type'       => 'video',
                'eventType'  => 'live',
                'channelId'  => $channelId,
                'part'       => 'id, snippet',
                'maxResults' => $maxResults
            );

            if (!empty($order)) {
                $params['order'] = $order;
            }

            return $this->research($params);
        }

        public function research($params, $pageInfo = false)
        {
            $urlApi = $this->get('search.list');

            if (empty($params) || !isset($params['q'])) {
                throw new \InvalidArgumentException('at least the Search query must be supplied');
            }

            $apiData = $this->call($urlApi, $params);

            if ($pageInfo) {
                return array(
                    'results' => $this->makeList($apiData),
                    'info'    => $this->page_info
                );
            } else {
                return $this->makeList($apiData);
            }
        }

        public function paginateResults($params, $token = null)
        {
            if (!is_null($token)) $params['pageToken'] = $token;

            if (!empty($params)) return $this->research($params, true);
        }

        public function getChannelByName($username, $optionalParams = false)
        {
            $urlApi = $this->get('channels.list');

            $params = array(
                'forUsername'   => $username,
                'part'          => 'id,snippet,contentDetails,statistics,invideoPromotion'
            );

            if($optionalParams){
                $params = array_merge($params, $optionalParams);
            }

            $apiData = $this->call($urlApi, $params);

            return $this->getSingle($apiData);
        }

        public function getChannelById($id, $optionalParams = false)
        {
            $urlApi = $this->get('channels.list');

            $params = array(
                'id'    => $id,
                'part'  => 'id,snippet,contentDetails,statistics,invideoPromotion'
            );

            if($optionalParams){
                $params = array_merge($params, $optionalParams);
            }

            $apiData = $this->call($urlApi, $params);

            return $this->getSingle($apiData);
        }

        public function getPlaylistsByChannelId($channelId, $optionalParams = array())
        {
            $urlApi = $this->get('playlists.list');

            $params = array(
                'channelId' => $channelId,
                'part'      => 'id, snippet, status'
            );

            if ($optionalParams) {
                $params = array_merge($params, $optionalParams);
            }

            $apiData = $this->call($urlApi, $params);

            return $this->makeList($apiData);
        }

        public function getPlaylistById($id)
        {
            $urlApi = $this->get('playlists.list');

            $params = array(
                'id'    => $id,
                'part'  => 'id, snippet, status'
            );

            $apiData = $this->call($urlApi, $params);

            return $this->getSingle($apiData);
        }

        public function getPlaylistItemsByPlaylistId($playlistId, $maxResults = 50)
        {
            $params = array(
                'playlistId'    => $playlistId,
                'part'          => 'id, snippet, contentDetails, status',
                'maxResults'    => $maxResults
            );

            return $this->getPlaylistItemsByPlaylistIdAdvanced($params);
        }

        public function getPlaylistItemsByPlaylistIdAdvanced($params, $pageInfo = false)
        {
            $urlApi = $this->get('playlistItems.list');

            if (empty($params) || !isset($params['playlistId'])) {
                throw new \InvalidArgumentException('at least the playlist id must be supplied');
            }

            $apiData = $this->call($urlApi, $params);

            if ($pageInfo) {
                return array(
                    'results' => $this->makeList($apiData),
                    'info'    => $this->page_info
                );
            } else {
                return $this->makeList($apiData);
            }
        }

        public function getActivitiesByChannelId($channelId)
        {
            if (empty($channelId)) {
                throw new \InvalidArgumentException('ChannelId must be supplied');
            }

            $urlApi = $this->get('activities');

            $params = array(
                'channelId' => $channelId,
                'part'      => 'id, snippet, contentDetails'
            );

            $apiData = $this->call($urlApi, $params);

            return $this->makeList($apiData);
        }

        public static function parseVIdFromURL($youtube_url)
        {
            $videoId = null;

            if (strpos($youtube_url, 'youtube.com')) {
                if (strpos($youtube_url, 'embed')) {
                    $path       = static::parse($youtube_url);
                    $videoId    = substr($path, 7);
                }

                if($params = static::url($youtube_url)) {
                    $videoId = isset($params['v']) ? $params['v'] : null;
                }
            } else if (strpos($youtube_url, 'youtu.be')) {
                $path       = static::parse($youtube_url);
                $videoId    = substr($path, 1);
            }

            if (empty($videoId)) {
                throw new \Exception('The supplied URL does not look like a Youtube URL');
            }

            return $videoId;
        }

        public function getChannelFromURL($youtube_url)
        {
            if (strpos($youtube_url, 'youtube.com') === false) {
                exception('Youtube', 'The supplied URL does not look like a Youtube URL');
            }

            $path = static::parse($youtube_url);

            if (strpos($path, '/channel') === 0) {
                $segments   = explode('/', $path);
                $channelId  = $segments[count($segments) - 1];
                $channel    = $this->getChannelById($channelId);
            } else if (strpos($path, '/user') === 0) {
                $segments   = explode('/', $path);
                $username   = $segments[count($segments) - 1];
                $channel    = $this->getChannelByName($username);
            } else {
                throw new \Exception('The supplied URL does not look like a Youtube Channel URL');
            }

            return $channel;
        }

        public function get($name)
        {
            return $this->APIs[$name];
        }

        public function getSingle(&$apiData)
        {
            $resObj = json_decode($apiData);

            if (isset($resObj->error)) {
                $msg = "Error " . $resObj->error->code . " " . $resObj->error->message;

                if (isset($resObj->error->errors[0])) {
                    $msg .= " : " . $resObj->error->errors[0]->reason;
                }

                throw new \Exception($msg, $resObj->error->code);
            } else {
                $itemsArray = $resObj->items;
                if (!is_array($itemsArray) || count($itemsArray) == 0) {
                    return false;
                } else {
                    return $itemsArray[0];
                }
            }
        }

        public function makeList(&$apiData)
        {
            $resObj = json_decode($apiData);

            if (isset($resObj->error)) {
                $msg = "Error " . $resObj->error->code . " " . $resObj->error->message;

                if (isset($resObj->error->errors[0])) {
                    $msg .= " : " . $resObj->error->errors[0]->reason;
                }
                throw new \Exception($msg, $resObj->error->code);
            } else {
                $this->page_info = array(
                    'resultsPerPage' => $resObj->pageInfo->resultsPerPage,
                    'totalResults'   => $resObj->pageInfo->totalResults,
                    'kind'           => $resObj->kind,
                    'etag'           => $resObj->etag,
                    'prevPageToken'	 => NULL,
    				'nextPageToken'	 => NULL
                );

                if(isset($resObj->prevPageToken)){
                    $this->page_info['prevPageToken'] = $resObj->prevPageToken;
                }

                if(isset($resObj->nextPageToken)){
                    $this->page_info['nextPageToken'] = $resObj->nextPageToken;
                }

                $itemsArray = $resObj->items;

                if (!is_array($itemsArray) || count($itemsArray) == 0) {
                    return false;
                } else {
                    return $itemsArray;
                }
            }
        }

        public function call($url, $params)
        {
            $params['key'] = $this->youtube_key;

            $tuCurl = curl_init();

            curl_setopt(
                $tuCurl,
                CURLOPT_URL,
                $url . (strpos($url, '?') === false ? '?' : '') . http_build_query($params)
            );

            if (strpos($url, 'https') === false) {
                curl_setopt($tuCurl, CURLOPT_PORT, 80);
            } else {
                curl_setopt($tuCurl, CURLOPT_PORT, 443);
            }

            if ($this->referer !== null) {
                curl_setopt($tuCurl, CURLOPT_REFERER, $this->referer);
            }

            curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);

            $tuData = curl_exec($tuCurl);

            if (curl_errno($tuCurl)) {
                throw new \Exception('Curl Error : ' . curl_error($tuCurl), curl_errno($tuCurl));
            }

            return $tuData;
        }

        public static function parse($url)
        {
            return parse_url($url, PHP_URL_PATH);
        }

        public static function url($url)
        {
            $queryString = parse_url($url, PHP_URL_QUERY);

            $params = array();

            parse_str($queryString, $params);

            if (is_null($params)) {
                return array();
            }

            return array_filter($params);
        }

        public static function getIdFromUrl($url)
        {
            if (empty($url)) {
               return;
            }

            preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);

            return $matches[1];
        }
    }
