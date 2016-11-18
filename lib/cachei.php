<?php
    namespace Octo;

    interface CacheI
    {
        public function set($k, $v, $expire = null);
        public function put($k, $v, $expire = null);
        public function get($k, $d = null);
        public function has($k);
        public function age($k);
        public function del($k);
        public function delete($k);
        public function remove($k);
        public function forget($k);
        public function destroy($k);
        public function incr($k, $by = 1);
        public function increment($k, $by = 1);
        public function decr($k, $by = 1);
        public function decrement($k, $by = 1);
        public function keys($pattern = '*');
        public function setMany(array $values, $e = null);
        public function many(array $keys);
        public function setnx($key, $value, $expire = null);
        public function setExpireAt($k, $v, $timestamp);
        public function setExp($k, $v, $expire);
        public function setExpire($k, $v, $expire);
        public function expire($k, $expire);
        public function expireAt($k, $timestamp);
        public function getOr($k, callable $c, $e = null);
        public function remember($k, $c, $e = null);
        public function watch($k, callable $exists = null, callable $notExists = null);
        public function session($k, $v = 'dummyget', $e = null);
        public function my($k, $v = 'dummyget', $e = null);
        public function aged($k, callable $c, $a);
        public function flush($pattern = '*');
        public function clean($pattern = '*');
        public function readAndDelete($key, $default = null);
        public function rename($keyFrom, $keyTo, $default = null);
        public function copy($keyFrom, $keyTo);
        public function getSize($key);
        public function length($key);
        public function hset($hash, $key, $value);
        public function hsetnx($hash, $key, $value);
        public function hget($hash, $key, $default = null);
        public function hstrlen($hash, $key);
        public function hgetOr($hash, $k, callable $c);
        public function hwatch($hash, $k, callable $exists = null, callable $notExists = null);
        public function hReadAndDelete($hash, $key, $default = null);
        public function hdelete($hash, $key);
        public function hdel($hash, $key);
        public function hhas($hash, $key);
        public function hexists($hash, $key);
        public function hincr($hash, $key, $by = 1);
        public function hdecr($hash, $key, $by = 1);
        public function hgetall($hash);
        public function hvals($hash);
        public function hlen($hash);
        public function hremove($hash);
        public function hkeys($hash);
        public function sadd($key, $value);
        public function scard($key);
        public function sinter();
        public function sunion();
        public function sinterstore();
        public function sunionstore();
        public function sismember($hash, $key);
        public function smembers($key);
        public function srem($hash, $key);
        public function smove($from, $to, $key);
        public function until($k, callable $c, $maxAge = null, $args = []);
        public function flash($key, $val = 'octodummy');
        public function add($k, $v, $e);
        public function setNow($k, $v, $expire = null);
        public function hasNow($k);
        public function getNow($k, $d = null);
        public function delNow($k);
        public function ageNow($k);
        public function getDel($k, $d = null);
    }