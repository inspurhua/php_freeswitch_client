<?php
/**
 * Created by PhpStorm.
 * User: zhanghua
 * Date: 2018/5/25
 * Time: 17:16
 */

class FsResponse
{
    private $data;
    private $content_type;//auth/request|command/reply|api/response|text/event-json
    static $last_content_type;
    private $content_length;
    private $reply_text;//+OK|-ERR
    private $content = [false, null];//第一个是匹配返回值了吗，第二个是返回值

    public function __construct($data)
    {
        $this->data = $data;
        $tmp = explode("\n\n", $this->data);
        $max = count($tmp) - 1;

        $body = isset($tmp[$max]) ? $tmp[$max] : '';
        $header = '';
        if ($max - 1 == 0) {
            $header = $tmp[0];
        } else {
            for ($i = 0; $i < $max; $i++) {
                $header .= $tmp[$i];
            }
        }

        $head = explode("\n", $header);
        foreach ($head as $text) {
            $ok = $this->getSegment("/Content-Type: (.*)/", $text);
            if ($ok) {
                $this->content_type = $ok;
                self::$last_content_type = $ok;
                break;
            }
        }
        foreach ($head as $text) {
            $ok = $this->getSegment("/Content-Length: (.*)/", $text);
            if ($ok) {
                $this->content_length = $ok;
                break;
            }
        }
        foreach ($head as $text) {
            $ok = $this->getSegment("/Reply-Text: (\+OK|-ERR) (.*)/", $text);
            if ($ok) {
                $this->reply_text = $ok;
                break;
            }
        }
        if (self::$last_content_type == 'api/response') {
            $this->content = [false, ''];
            if (strlen($body) == 0) {
                $body = $header;
            }
            $ok = preg_match("/(\+OK|-ERR)\s*?(.*)/", $body, $match);
            if ($ok) {
//                $bool = ($match[1] == '+OK') ? true : false;
                $this->content = [true, $match[2]];
            }
        }
        if (self::$last_content_type == 'text/event-json') {
            if (empty($body)) {
                $body = $header;
            }
            $a_content = json_decode($body, true);
            if (is_null($a_content)) {
                $this->content = [false, null];
            }
            $this->content = [true, $a_content];
        }
    }

    public function getContentType()
    {
        return $this->content_type ?
            $this->content_type :
            self::$last_content_type;
    }

    public function getReplyText()
    {
        return $this->reply_text;
    }

    public function getContent()
    {
        return $this->content;
    }

    private function getSegment($regex, $text)
    {
        $ok = preg_match($regex, $text, $match);
        if ($ok) {
            return $match[1];
        } else {
            return '';
        }
    }

}
