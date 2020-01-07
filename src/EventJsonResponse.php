<?php
ini_set('memory_limit','512M');

class EventJsonResponse
{
    static $last_content;
    private $head = true;
    private $tail = true;

    public function __construct($data)
    {
        $this->head = (substr($data, 0, 16) == 'Content-Length: ');
        $this->tail = (substr($data, -1) == '}');

        if ($this->head) {
            //是头直接赋值
            self::$last_content = $data;
        } else {
            //不是头追加
            self::$last_content .= $data;
        }
    }


    public function get_content()
    {
        if ($this->tail) {
            $data = self::$last_content;
            self::$last_content = '';
            $count = 0;
            $data = preg_replace("/(Content-Length: \d+\nContent-Type: text\/event-json\n\n)/", '<<ZH>>', $data, -1, $count);
            $a_data = explode('<<ZH>>', $data);
            return array_slice($a_data, count($a_data) - $count, $count);

        } else {
            return [];
        }
    }
}
