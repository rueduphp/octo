<?php

    namespace Octo;

    class Pop3
    {
        public $ERROR      = '';
        public $TIMEOUT    = 60;
        public $COUNT      = -1;
        public $BUFFER     = 512;
        public $FP         = '';
        public $MAILSERVER = '';
        public $DEBUG      = FALSE;
        public $BANNER     = '';
        public $ALLOWAPOP  = FALSE;

        public function __construct ( $server = '', $timeout = '' )
        {
            settype($this->BUFFER, "integer");

            if (!empty($server)) {
                if (empty($this->MAILSERVER)) $this->MAILSERVER = $server;
            }

            if (!empty($timeout)) {
                settype($timeout, "integer");
                $this->TIMEOUT = $timeout;

                if (!ini_get('safe_mode')) set_time_limit($timeout);
            }
        }

        public function update_timer ()
        {
            if (!ini_get('safe_mode')) set_time_limit($this->TIMEOUT);

            return true;
        }

        public function connect ($server, $port = 110)
        {
            if (!isset($port) || !$port) {$port = 110;}

            if(!empty($this->MAILSERVER))  $server = $this->MAILSERVER;

            if(empty($server)){
                $this->ERROR = "POP3 connect: " . _("No server specified");
                unset($this->FP);

                return false;
            }

            $fp = @fsockopen("$server", $port, $errno, $errstr);

            if(!$fp) {
                $this->ERROR = "POP3 connect: " . _("Error ") . "[$errno] [$errstr]";
                unset($this->FP);

                return false;
            }

            socket_set_blocking($fp,-1);
            $this->update_timer();
            $reply = fgets($fp,$this->BUFFER);
            $reply = $this->strip_clf($reply);

            if($this->DEBUG)  error_log("POP3 SEND [connect: $server] GOT [$reply]", 0);

            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 connect: " . _("Error ") . "[$reply]";
                unset($this->FP);

                return false;
            }

            $this->FP = $fp;
            $this->BANNER = $this->parse_banner($reply);

            return true;
        }

        public function user ($user = "")
        {
            if( empty($user) ) {
                $this->ERROR = "POP3 user: " . _("no login ID submitted");

                return false;
            } elseif(!isset($this->FP)) {
                $this->ERROR = "POP3 user: " . _("connection not established");

                return false;
            } else {
                $reply = $this->send_cmd("USER $user");
                if(!$this->is_ok($reply)) {
                    $this->ERROR = "POP3 user: " . _("Error ") . "[$reply]";

                    return false;
                } else return true;
            }
        }

        public function pass ($pass = "")
        {
            if(empty($pass)) {
                $this->ERROR = "POP3 pass: " . _("No password submitted");

                return false;
            } elseif(!isset($this->FP)) {
                $this->ERROR = "POP3 pass: " . _("connection not established");

                return false;
            } else {
                $reply = $this->send_cmd("PASS $pass");

                if(!$this->is_ok($reply)) {
                    $this->ERROR = "POP3 pass: " . _("Authentication failed") . " [$reply]";
                    $this->quit();

                    return false;
                } else {
                    $count = $this->last("count");
                    $this->COUNT = $count;
                    return $count;
                }
            }
        }

        public function apop ($login,$pass)
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 apop: " . _("No connection to server");

                return false;
            } elseif(!$this->ALLOWAPOP) {
                $retVal = $this->login($login,$pass);

                return $retVal;
            } elseif(empty($login)) {
                $this->ERROR = "POP3 apop: " . _("No login ID submitted");

                return false;
            } elseif(empty($pass)) {
                $this->ERROR = "POP3 apop: " . _("No password submitted");

                return false;
            } else {
                $banner = $this->BANNER;
                if( (!$banner) or (empty($banner)) ) {
                    $this->ERROR = "POP3 apop: " . _("No server banner") . ' - ' . _("abort");
                    $retVal = $this->login($login,$pass);

                    return $retVal;
                } else {
                    $AuthString = $banner;
                    $AuthString .= $pass;
                    $APOPString = md5($AuthString);
                    $cmd = "APOP $login $APOPString";
                    $reply = $this->send_cmd($cmd);
                    if(!$this->is_ok($reply)) {
                        $this->ERROR = "POP3 apop: " . _("apop authentication failed") . ' - ' . _("abort");
                        $retVal = $this->login($login,$pass);

                        return $retVal;
                    } else {
                        //  Auth successful.
                        $count = $this->last("count");
                        $this->COUNT = $count;

                        return $count;
                    }
                }
            }
        }

        public function login ($login = "", $pass = "")
        {
            if( !isset($this->FP) ) {
                $this->ERROR = "POP3 login: " . _("No connection to server");

                return false;
            } else {
                $fp = $this->FP;
                if ( !$this->user( $login ) ) {
                    return false;
                } else {
                    $count = $this->pass($pass);
                    if ((!$count) || ($count == -1)) return false;
                    else return $count;
                }
            }
        }

        public function top ($msgNum, $numLines = "0")
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 top: " . _("No connection to server");

                return false;
            }

            $this->update_timer();

            $fp = $this->FP;
            $buffer = $this->BUFFER;
            $cmd = "TOP $msgNum $numLines";

            fwrite($fp, "TOP $msgNum $numLines\r\n");

            $reply = fgets($fp, $buffer);

            $reply = $this->strip_clf($reply);

            if($this->DEBUG) {
                @error_log("POP3 SEND [$cmd] GOT [$reply]",0);
            }

            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 top: " . _("Error ") . "[$reply]";

                return false;
            }

            $count = 0;
            $MsgArray = array();

            $line = fgets($fp,$buffer);

            while ( !preg_match('/^\.\r\n/',$line)) {
                $MsgArray[$count] = $line;
                $count++;
                $line = fgets($fp,$buffer);

                if(empty($line)) { break; }
            }

            return $MsgArray;
        }

        public function pop_list ($msgNum = "")
        {
            if(!isset($this->FP))
            {
                $this->ERROR = "POP3 pop_list: " . _("No connection to server");
                return false;
            }

            $fp = $this->FP;
            $Total = $this->COUNT;

            if ((!$Total) or ($Total == -1)) {
                return false;
            }

            if ($Total == 0) {
                return array("0","0");
            }

            $this->update_timer();

            if(!empty($msgNum)) {
                $cmd = "LIST $msgNum";

                fwrite($fp,"$cmd\r\n");

                $reply = fgets($fp,$this->BUFFER);

                $reply = $this->strip_clf($reply);

                if($this->DEBUG) {
                    @error_log("POP3 SEND [$cmd] GOT [$reply]",0);
                }

                if(!$this->is_ok($reply)) {
                    $this->ERROR = "POP3 pop_list: " . _("Error ") . "[$reply]";

                    return false;
                }

                list($junk,$num,$size) = preg_split('/\s+/',$reply);

                return $size;
            }

            $cmd = "LIST";
            $reply = $this->send_cmd($cmd);

            if(!$this->is_ok($reply)) {
                $reply = $this->strip_clf($reply);
                $this->ERROR = "POP3 pop_list: " . _("Error ") .  "[$reply]";

                return false;
            }

            $MsgArray = array();
            $MsgArray[0] = $Total;

            for($msgC=1;$msgC <= $Total; $msgC++) {
                if($msgC > $Total) { break; }

                $line = fgets($fp,$this->BUFFER);
                $line = $this->strip_clf($line);

                if(strpos($line, '.') === 0) {
                    $this->ERROR = "POP3 pop_list: " . _("Premature end of list");

                    return false;
                }

                list($thisMsg,$msgSize) = preg_split('/\s+/',$line);
                settype($thisMsg,"integer");

                if($thisMsg != $msgC) {
                    $MsgArray[$msgC] = "deleted";
                } else {
                    $MsgArray[$msgC] = $msgSize;
                }
            }

            return $MsgArray;
        }

        public function get ($msgNum)
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 get: " . _("No connection to server");

                return false;
            }

            $this->update_timer();

            $fp = $this->FP;
            $buffer = $this->BUFFER;
            $cmd = "RETR $msgNum";
            $reply = $this->send_cmd($cmd);

            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 get: " . _("Error ") . "[$reply]";

                return false;
            }

            $count = 0;
            $MsgArray = array();

            $line = fgets($fp,$buffer);

            while ( !preg_match('/^\.\r\n/',$line)) {
                if ( $line{0} == '.' ) { $line = substr($line,1); }

                $MsgArray[$count] = $line;
                $count++;
                $line = fgets($fp,$buffer);

                if(empty($line))    { break; }
            }

            return $MsgArray;
        }

        public function last ( $type = "count" )
        {
            $last = -1;

            if(!isset($this->FP)) {
                $this->ERROR = "POP3 last: " . _("No connection to server");

                return $last;
            }

            $reply = $this->send_cmd("STAT");

            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 last: " . _("Error ") . "[$reply]";

                return $last;
            }

            $Vars = preg_split('/\s+/',$reply);
            $count = $Vars[1];
            $size = $Vars[2];

            settype($count,"integer");
            settype($size,"integer");

            if($type != "count") {
                return array($count,$size);
            }

            return $count;
        }

        public function reset ()
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 reset: " . _("No connection to server");

                return false;
            }

            $reply = $this->send_cmd("RSET");

            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 reset: " . _("Error ") . "[$reply]";
                @error_log("POP3 reset: ERROR [$reply]",0);
            }

            $this->quit();

            return true;
        }

        public function send_cmd ( $cmd = "" )
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 send_cmd: " . _("No connection to server");

                return false;
            }

            if(empty($cmd))
            {
                $this->ERROR = "POP3 send_cmd: " . _("Empty command string");

                return "";
            }

            $fp = $this->FP;
            $buffer = $this->BUFFER;
            $this->update_timer();

            fwrite($fp,"$cmd\r\n");

            $reply = fgets($fp,$buffer);
            $reply = $this->strip_clf($reply);

            if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

            return $reply;
        }

        public function quit()
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 quit: " . _("connection does not exist");

                return false;
            }

            $fp = $this->FP;
            $cmd = "QUIT";

            fwrite($fp,"$cmd\r\n");

            $reply = fgets($fp,$this->BUFFER);

            $reply = $this->strip_clf($reply);

            if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

            fclose($fp);
            unset($this->FP);

            return true;
        }

        public function popstat ()
        {
            $PopArray = $this->last("array");

            if($PopArray == -1) { return false; }

            if( (!$PopArray) or (empty($PopArray)) ) {
                return false;
            }

            return $PopArray;
        }

        public function uidl ($msgNum = "")
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 uidl: " . _("No connection to server");

                return false;
            }

            $fp = $this->FP;
            $buffer = $this->BUFFER;

            if(!empty($msgNum)) {
                $cmd = "UIDL $msgNum";
                $reply = $this->send_cmd($cmd);

                if(!$this->is_ok($reply)) {
                    $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";

                    return false;
                }

                list ($ok,$num,$myUidl) = preg_split('/\s+/',$reply);

                return $myUidl;
            } else {
                $this->update_timer();

                $UIDLArray = array();
                $Total = $this->COUNT;
                $UIDLArray[0] = $Total;

                if ($Total < 1)  {
                    return $UIDLArray;
                }

                $cmd = "UIDL";

                fwrite($fp, "UIDL\r\n");

                $reply = fgets($fp, $buffer);

                $reply = $this->strip_clf($reply);

                if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

                if(!$this->is_ok($reply)) {
                    $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";

                    return false;
                }

                $line = "";
                $count = 1;
                $line = fgets($fp,$buffer);

                while ( !preg_match('/^\.\r\n/',$line)) {
                    list ($msg,$msgUidl) = preg_split('/\s+/',$line);

                    $msgUidl = $this->strip_clf($msgUidl);

                    if($count == $msg) {
                        $UIDLArray[$msg] = $msgUidl;
                    } else {
                        $UIDLArray[$count] = 'deleted';
                    }

                    $count++;

                    $line = fgets($fp,$buffer);
                }
            }

            return $UIDLArray;
        }

        public function delete ($msgNum = "")
        {
            if(!isset($this->FP)) {
                $this->ERROR = "POP3 delete: " . _("No connection to server");

                return false;
            }

            if(empty($msgNum)) {
                $this->ERROR = "POP3 delete: " . _("No msg number submitted");

                return false;
            }

            $reply = $this->send_cmd("DELE $msgNum");

            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 delete: " . _("Command failed ") . "[$reply]";

                return false;
            }

            return true;
        }

        public function is_ok ($cmd = "")
        {
            if( empty($cmd) ) return false;
            else return( stripos($cmd, '+OK') !== false );
        }

        public function strip_clf ($text = "")
        {
            if(empty($text)) return $text;
            else {
                $stripped = str_replace(array("\r","\n"),'',$text);

                return $stripped;
            }
        }

        public function parse_banner ( $server_text )
        {
            $outside = true;
            $banner = "";
            $length = strlen($server_text);

            for($count =0; $count < $length; $count++) {
                $digit = substr($server_text,$count,1);

                if(!empty($digit)) {
                    if( (!$outside) && ($digit != '<') && ($digit != '>') ) {
                        $banner .= $digit;
                    }

                    if ($digit == '<') {
                        $outside = false;
                    }

                    if($digit == '>') {
                        $outside = true;
                    }
                }
            }

            $banner = $this->strip_clf($banner);

            return "<$banner>";
        }
    }
