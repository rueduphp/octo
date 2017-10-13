<?php
    namespace Octo;

    use SessionHandlerInterface as Handler;

    class OctaliaSession implements Handler
    {
        private $db;

        public function __construct()
        {
            $this->db = em('systemSession');
            def('SESSION_DURATION', conf('SESSION_DURATION', 3600), true);
        }

        public function open($savePath = null, $sessionName = null)
        {
            return true;
        }

        public function close()
        {
            return true;
        }

        public function read($session_id)
        {
            $row = $this->db->firstOrCreate([
                'session_id' => $session_id
            ]);

            if (!$row['data']) {
                return '';
            }

            return $row['data'];
        }

        public function write($session_id, $data)
        {
            $row = $this->db->firstOrCreate([
                'session_id' => $session_id
            ]);

            try {
                $expiry_time    = time() + SESSION_DURATION;
                $row->data      = $data;
                $row->expiry    = $expiry_time;

                $row->save();

                return true;
            } catch (\Exception $error) {
                error_log($error);

                exit;
            }

            return false;
        }

        public function destroy($session_id)
        {
            try {
                $this->db->firstOrCreate([
                    'session_id' => $session_id
                ])->delete();

                return true;
            } catch (\Exception $error) {
                error_log($error);

                exit;
            }

            return false;
        }

        public function gc($maxlifetime = SESSION_DURATION)
        {
            try {
                $this->db->where('expiry', '<', time())->delete();
                    return true;
            } catch (\Exception $error) {
                error_log($error);

                exit;
            }

            return false;
        }
    }
    /**
     * $session_handler = new OctaliaSession();
     * session_set_save_handler($session_handler, true);
     * session_name('MySessionName');
     * start_session();
     * session_regenerate_id(true);
     */
