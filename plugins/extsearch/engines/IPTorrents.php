<?php

class IPTorrentsEngine extends commonEngine
{
    public $defaults = ["public" => false, "page_size" => 50, "cookies" => "iptorrents.com|pass=XXX;uid=XXX;"];
    public $categories = ['all'    => '',
                          'Movies' => '72', 'TV' => '73', 'Games' => '74', 'Music' => '75', 'Miscellaneous' => '76', 'XXX' => '88'];

    protected static $seconds = [
        'minutes' => 60,
        'hours'   => 3600,
        'days'    => 86400,
        'weeks'   => 604800,
        'months'  => 2592000,
        'years'   => 31536000,
    ];

    protected static function getTime($now, $ago, $unit)
    {
        $delta = (array_key_exists($unit, self::$seconds) ? self::$seconds[$unit] : 0);
        return ($now - ($ago * $delta));
    }

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        $url = 'https://iptorrents.com';
        $now = time();
        if ($useGlobalCats)
            $categories = ['all'    => '',
                           'movies' => '72', 'tv' => '73', 'music' => '75', 'games' => '74',
                           'anime'  => '60', 'software' => '1;86', 'pictures' => '36', 'books' => '35;64;94'];
        else
            $categories = &$this->categories;
        if (!array_key_exists($cat, $categories))
            $cat = $categories['all'];
        else
            $cat = $categories[$cat];
        for ($pg = 1; $pg < 11; $pg++) {
            $cli = $this->fetch($url . '/t?' . $cat . ';o=seeders;q=' . $what . ';p=' . $pg);
            if (($cli == false) || (strpos($cli->results, ">No Torrents Found!<") !== false) ||
                (strpos($cli->results, 'name="password') !== false))
                break;

            $doc = new DOMDocument();
            @$doc->loadHTML($cli->results);

            $skipped_first = false;
            $table = $doc->getElementById('torrents');

            if ($table) {
                foreach ($table->getElementsByTagName('tr') as $tr) {
                    if (!$skipped_first) {
                        $skipped_first = true;
                        continue;
                    }

                    $tds = $tr->getElementsByTagName('td');
                    if ($tds->length !== 9) continue; //bail if table rows isn't as expected

                    try {
                        preg_match('/\| (.*) (minutes|hours|days|weeks|months|years) ago/',
                            $tds[1]->textContent,
                            $ago_matches
                        );

                        $item = $this->getNewEntry();
                        $item["cat"] = $tds[0]->getElementsByTagName('img')[0]->getAttribute('alt');
                        $item["desc"] = $url . $tds[3]->getElementsByTagName('a')[0]->getAttribute('href');
                        $item["name"] = self::removeTags($tds[1]->getElementsByTagName('a')[0]->textContent);
                        $item["size"] = self::formatSize($tds[5]->textContent);
                        $item["time"] = self::getTime($now, $ago_matches[1], $ago_matches[2]);
                        $item["seeds"] = intval($tds[7]->textContent);
                        $item["peers"] = intval($tds[8]->textContent);
                        $ret[$item['desc']] = $item;
                    } catch (Exception $e) {
                        //table row wasn't in the correct format
                    }
                }
            }
        }
    }
}
