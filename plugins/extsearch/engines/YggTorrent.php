<?php

class YggTorrentEngine extends commonEngine
{
    const URL = 'yggtorrent.com';
    const SCHEME = 'https://';
    const PAGE_SIZE = 15;

    const CATEGORY_MAPPING = array(
        'filmvidéo' => 'Vidéos',
        'série-tv' => 'Séries',
        'animation-série' => 'Animation',
        'jeu-vidéo' => 'Jeux',
        'emission-tv' => 'Emission TV',
        'vidéo-clips' => 'Clip Vidéo',
        'bds' => 'Bande dessinée'
    );

    public $defaults = array("public" => false, "page_size" => self::PAGE_SIZE, "cookies" => self::URL . "|ci_session=XXX");

    // No search filters for now
    public $categories = array(
        'Tout' => '',
    );

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        $added = 0;
        $what = rawurlencode(rawurldecode($what));

        for ($pg = 0; $pg < (self::PAGE_SIZE * 9); $pg += self::PAGE_SIZE) {
            $search = self::SCHEME . self::URL . '/engine/search?q=' . $what . '&page=' . $pg;
            $cli = $this->fetch($search);
            if (($cli == false) || (strpos($cli->results, "download_torrent") === false)) {
                break;
            }

            $res = preg_match_all(
                '`<tr>.*<a class="torrent-name" href="(?P<desc>.*)">(?P<name>.*)</a>' .
                '.*<a.*/download_torrent\?id=(?P<id>.*)">.*<td><i.*>.*</i>(?P<date>.*)</td>.*<td>(?P<size>.*)</td>' .
                '.*<td.*>(?P<seeder>.*)</td.*>.*<td.*>(?P<leecher>.*)</td.*>.*</tr>`siU',
                $cli->results,
                $matches
            );

            if ($res) {
                for ($i = 0; $i < $res; $i++) {
                    $link = self::SCHEME . self::URL . "/engine/download_torrent?id=" . $matches["id"][$i];
                    if (!array_key_exists($link, $ret)) {
                        $item = $this->getNewEntry();
                        $item["desc"] = $matches["desc"][$i];
                        $item["name"] = self::removeTags($matches["name"][$i]);

                        // The parsed size has the format XX.XXGB, we need to add a space to help a bit the formatSize method
                        $item["size"] = self::formatSize(preg_replace('/([0-9.]+)(\w+)/', '$1 $2', $matches["size"][$i]));

                        // To be able to display categories, we need to parse them directly from the torrent URL
                        $cat = preg_match_all('`https://yggtorrent.com/torrent/(?P<cat1>.*)/(?P<cat2>.*)/`', $item['desc'], $catRes);
                        if ($cat) {
                            $cat1 = $this->getPrettyCategoryName($catRes['cat1'][0]);
                            $cat2 = $this->getPrettyCategoryName($catRes['cat2'][0]);
                            $item["cat"] = $cat1 . ' > ' . $cat2;
                        }

                        // We only have the time since the upload, so let's try to convert that...
                        $item["time"] = strtotime(self::removeTags($this->getStrToTimeCompatibleDate($matches["date"][$i])));

                        $item["seeds"] = intval(self::removeTags($matches["seeder"][$i]));
                        $item["peers"] = intval(self::removeTags($matches["leecher"][$i]));
                        $ret[$link] = $item;
                        $added++;
                        if ($added >= $limit) {
                            return;
                        }
                    }
                }
            } else {
                break;
            }
        }
    }

    private function getPrettyCategoryName($input)
    {
        if (array_key_exists($input, self::CATEGORY_MAPPING)) {
            return self::CATEGORY_MAPPING[$input];
        } else {
            return ucwords($input);
        }
    }

    private function getStrToTimeCompatibleDate($input)
    {
        $date = preg_split("/il y a /", $input)[1];
        $date = preg_replace("/(\d+) seconde.*/", "-$1 second", $date);
        $date = preg_replace("/(\d+) heure.*/", "-$1 hour", $date);
        $date = preg_replace("/(\d+) minute.*/", "-$1 minute", $date);
        $date = preg_replace("/(\d+) jour.*/", "-$1 day", $date);
        $date = preg_replace("/(\d+) mois.*/", "-$1 month", $date);

        return $date;
    }
}
