<?php

namespace telemanpl;


/**
 * All channels http://www.teleman.pl/moje-stacje
 * Programs for channel and day http://www.teleman.pl/program-tv/stacje/Puls?date=2016-05-13&hour=-1 - channel Puls for 2016-05-13 - hour=-1 - за весь день
 * If channel name consists of several words, url must be like http://www.teleman.pl/program-tv/stacje/Filmbox-Extra-HD
 *
 * Class EpgParser
 * @property \DateTime $currentDay
 * @property array $categories Channels categories(sections)
 * @property EpgChannelParser $channelParser
 * @package telemanpl
 */
class EpgParser extends BaseEpgParser
{

    protected $programs = array();
    protected $programParser;

    protected $currentChannel = null;
    protected $currentChannelUrl = null;
    protected $currentDay;

    /**
     * @param array $options
     */
    public function __construct($options = array()) {
        parent::__construct($options);
        $this->channelParser = new EpgChannelParser($options);
        $this->programParser = new EpgProgramParser($options);
    }


    /**
     * @return bool|null
     */
    public function loadChannels() {
        return $this->channelParser->loadChannels();
    }

    /**
     * @param $page
     * @return bool
     */
    public function parseChannels($page) {
        //getting main channels info(id,name,category)
        if (!$this->channelParser->parseChannels($page)) {
            return false;
        }
        $index = $this->channelParser->loadIndexPage();

        if ($index) {
            $chanData = $this->channelParser->parseIndexPage($index);
            if ($chanData) {
                $found = $this->channelParser->getChannels();
                $foundHandled = array();
                foreach ($found as $f) {
                    $foundHandled[$f['name']] = $f;
                }
                //adding url to channel info array
                foreach ($chanData as $chan) {
                    if (isset($foundHandled[$chan['name']])) {
                        $foundHandled[$chan['name']]['url'] = $chan['url'];
                    }
                }
                $this->channelParser->setChannels(array_values($foundHandled));
            }
        }
        foreach ($this->channelParser->getErrors() as $err) {
            $this->setError($err);
        }
        return true;
    }

    /**
     * Parsing day's first program
     * @param string $day
     * @param string $channelName
     * @param string $channelUrl
     * @return bool
     */
    protected function parseDayFirstProgram($day, $channelName, $channelUrl) {
        try {
            $dayObject = new \DateTime($day);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
        $this->currentChannel = $channelName;
        $this->currentChannelUrl = $channelUrl;

        $urlData = parse_url($this->config['baseUrl']);

        $parts = parse_url($channelUrl);
        $url = $urlData['scheme'] . "://" . $urlData['host'] . "/" . trim($parts['path'], "/");

        $url = $url . "?date=" . $dayObject->format('Y-m-d') . "&hour=-1";
        $this->initCurl($url)->runCurl();
        if ($this->curlError) {
            $error = $this->curlError . "\n Url: " . $url . ";";
            if (isset($this->curlOptions[CURLOPT_PROXY])) {
                $error .= "\n Proxy: " . $this->curlOptions[CURLOPT_PROXY];
            }
            $this->setError($error);
            return false;
        }
        if ($this->curlInfo['http_code'] != '200') {
            $this->setError("http code is not OK or content is invalid " . $this->curlInfo['http_code'] . "/" . $this->curlInfo['content_type']);
            return false;
        }

        $page = $this->curlResult;

        if (!$page) {
            $this->setError("No page content is set");
            return false;
        }
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = true;
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        $dom->encoding = 'UTF-8';
        $main = $dom->getElementById("stationListing");
        if (!$main) {
            unset($dom);
            $this->setError("No main block");
            return false;
        }
        $uls = $main->getElementsByTagName('ul');
        if (!$uls || !$uls->length) {
            return false;
        }
        $programs = array();
        foreach ($uls as $ul) {
            /**
             * @var \DOMElement $ul
             */
            if ($ul->getAttribute('class') == 'stationItems') {
                $programWrappers = $ul->getElementsByTagName('li');
                if (!$programWrappers || !$programWrappers->length) {
                    break;
                }

                $programs = $this->parseStationItems($programWrappers, 1);
                break;
            }
        }
        if ($programs) {
            return current($programs);
        }
        return false;
    }


    /**
     *
     * @param string $day as Y-m-d
     * @param string|int $channel channel name or id. If you pass id - it should be strict int,eg not "13" but 13.
     * @param null|string $channelUrl url to channel (full or relative with leading slash) eg /program-tv/stacje/TVN-Siedem
     * @return array|boolean
     */
    public function loadDay($day, $channel, $channelUrl = null) {
        try {
            $dayObject = new \DateTime($day);
            $this->currentDay = $dayObject;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
        $url = null;
        $urlData = parse_url($this->config['baseUrl']);

        if ($channelUrl) {
            $parts = parse_url($channelUrl);
            $url = $urlData['scheme'] . "://" . $urlData['host'] . "/" . trim($parts['path'], "/");
        } else {
            //getting url from list of channels
            $channels = $this->channelParser->getChannels();
            if (!$channels) {
                $chanPage = $this->loadChannels();
                if (!$this->parseChannels($chanPage)) {
                    return false;
                }
            }
            $searchField = is_int($channel) ? "id" : "name";


            foreach ($this->channelParser->getChannels() as $chanInfo) {
                if (isset($chanInfo[$searchField]) && $chanInfo[$searchField] == (string)$channel) {
                    $this->currentChannel = $chanInfo['name'];
                    $url = $urlData['scheme'] . "://" . $urlData['host'] . $chanInfo['url'];
                    break;
                }
            }
        }

        if (!$url) {
            $this->setError("Failed to find chanel url for: " . $channel . "/" . $channelUrl);
            return false;
        }
        $this->currentChannelUrl = $url;

        $url = $url . "?date=" . $dayObject->format('Y-m-d') . "&hour=-1";
        $this->initCurl($url)->runCurl();
        if ($this->curlError) {
            $error = $this->curlError . "\n Url: " . $url . ";";
            if (isset($this->curlOptions[CURLOPT_PROXY])) {
                $error .= "\n Proxy: " . $this->curlOptions[CURLOPT_PROXY];
            }
            $this->setError($error);
            return false;
        }
        if ($this->curlInfo['http_code'] != '200') {
            $this->setError("http code is not OK or content is invalid " . $this->curlInfo['http_code'] . "/" . $this->curlInfo['content_type']);
            return false;
        }
//        file_put_contents($channel.".log",$this->curlResult);
        return $this->curlResult;
    }

    /**
     * @param $page
     * @param bool $extended Set true to parse additional data for each program(rating, director,bigger description, actors)
     * @return bool|array
     */
    public function parseDaySchedule($page, $extended = false) {
        if (!$page) {
            $this->setError("No page content is set");
            return false;
        }
        $this->programs = array();
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = true;
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        $dom->encoding = 'UTF-8';
        $main = $dom->getElementById("stationListing");
        if (!$main) {
            unset($dom);
            $this->setError("No main block");
            return false;
        }
        $uls = $main->getElementsByTagName('ul');
        if (!$uls || !$uls->length) {
            return false;
        }
        $urlData = parse_url($this->config['baseUrl']);
        foreach ($uls as $ul) {
            /**
             * @var \DOMElement $ul
             */
            if ($ul->getAttribute('class') == 'stationItems') {
                $programs = $this->parseStationItems($ul->getElementsByTagName('li'));
                if ($programs) {
                    foreach ($programs as $program) {
                        if ($extended) {
                            $progPage = $this->getProgramInfo($urlData['scheme'] . "://" . $urlData['host'] . $program['url']);
                            $progData = $this->parseProgramData($progPage);
                            if ($progData) {
                                foreach ($progData as $key => $v) {
                                    $program[$key] = $v;
                                }
                            }
                        }
                        array_push($this->programs, $program);
                    }
                }
            }
        }

        $this->calcProgramsLength();


        return $this->programs;
    }

    /**
     * Calculating program's length
     * Each program has only start time
     * To calculate last program's length we have to parse next day epg
     */
    protected function calcProgramsLength() {
        $day = $this->currentDay->format('Y-m-d');
        $before = null;

        foreach ($this->programs as $num => $program) {
            $nextDay = false;
            try {

                $previousStart = 0;
                if ($before && $before instanceof \DateTime) {
                    $previousStart = $before->getTimestamp();
                }
                list($hour, $minutes) = explode(":", $program['start']);
                if ($hour < 10) {
                    $hour = sprintf("%02d", $hour);
                }
                $time = $day . " " . $hour . ":" . $minutes . ":00";
                $start = new \DateTime($time);
                $before = $start;
                $shift = false;
                if ($start->getTimestamp() < $previousStart) {
                    //means that current program's time is earlier than previous -> current program starts after midnight (eg 00:40)
                    $shift = true;
                    $start->modify("+1 day");
                }
                if (isset($this->programs[$num + 1])) {
                    list($hourEnd, $minutesEnd) = explode(":", $this->programs[$num + 1]['start']);
                    if ($hourEnd < 10) {
                        $hourEnd = sprintf("%02d", $hourEnd);
                    }
                    $timeEnd = $day . " " . $hourEnd . ":" . $minutesEnd . ":00";

                    $end = new \DateTime($timeEnd);
                    if ($shift) {
                        $end->modify("+1 day");
                    }
                    if ($end->getTimestamp() < $start->getTimestamp())
                        //if current program's ending time is earlier then it's start (eg 23:40 - 00:20)
                        $end->modify("+1 day");
                } else {
                    if ($shift) {
                        $next = $start->format('Y-m-d');
                    } else {
                        $tmp = new \DateTime($start->format('Y-m-d H:i:s'));
                        $tmp->modify("+1 day");
                        $next = $tmp->format('Y-m-d');
                        unset($tmp);
                    }
                    $nextProgram = $this->parseDayFirstProgram($next, $this->currentChannel, $this->currentChannelUrl);
                    if (!$nextProgram) {
                        $this->setError("Failed to get very last program info; fallback to 6:00 am");
                        //fallback to 6:00 AM as end of very last program;
                        $timeEnd = $next . " 06:00:00";
                    } else {
                        list($hourEnd, $minutesEnd) = explode(":", $nextProgram['start']);
                        if ($hourEnd < 10) {
                            $hourEnd = sprintf("%02d", $hourEnd);
                        }
                        $timeEnd = $next . " " . $hourEnd . ":" . $minutesEnd . ":00";
                    }
                    $end = new \DateTime($timeEnd);
                    //need to parse next day
                }
                $this->programs[$num]['length'] = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                $this->programs[$num]['dateStart'] = $start->format('Y-m-d');
            } catch (\Exception $e) {
                $this->setError($e->getMessage());
                continue;
            }

        }
    }

    /**
     * @param \DOMNodeList $data
     * @param int|null $limit limit number of parsed programs
     * @return bool|array
     */
    protected function parseStationItems($data, $limit = null) {
        if (!$data || !$data->length) {
            return false;
        }
        $programs = array();
        foreach ($data as $li) {
            $program = array();
            /**
             * @var \DOMElement $li
             */
            $id = $li->getAttribute('id');
            if (strpos($id, 'prog') !== 0) {
                continue;
            }
            $class = $li->getAttribute('class');
            if (strpos($class, 'with-photo') !== false) {
                $photoLinks = $li->getElementsByTagName('a');
                if ($photoLinks && $photoLinks->length) {
                    /**
                     * @var \DOMElement $phLink
                     */
                    $phLink = $photoLinks->item(0);
                    $phImgs = $phLink->getElementsByTagName('img');
                    if ($phImgs && $phImgs->length) {
                        /**
                         * @var \DOMElement $img
                         */
                        $img = $phImgs->item(0);
                        $program['img'] = $img->getAttribute('src');
                    }
                }
            }

            $divs = $li->getElementsByTagName('div');
            if (!$divs || !$divs->length) {
                continue;
            }
            $ems = $li->getElementsByTagName('em');
            if (!$ems || !$ems->length) {
                $this->setError("No start time found");
                continue;
            }
            $startAt = $ems->item(0)->textContent;
            $program['start'] = $startAt;
            foreach ($divs as $d) {
                /**
                 * @var \DOMElement $d
                 */
                if ($d->getAttribute('class') == 'detail') {
                    $links = $d->getElementsByTagName('a');
                    if (!$links || !$links->length) {
                        break;
                    }
                    /**
                     * @var \DOMElement $link
                     */
                    $link = $links->item(0);
                    $href = $link->getAttribute('href');
                    $name = $link->textContent;
                    $program['url'] = $href;
                    $program['name'] = $name;
                    break;
                }

            }

            $paragraphs = $li->getElementsByTagName('p');
            if ($paragraphs && $paragraphs->length) {
                foreach ($paragraphs as $par) {
                    /**
                     * @var \DOMElement $par
                     */
                    if ($par->getAttribute('class') == 'genre') {
                        $program['genre'] = $par->textContent;
                    } elseif (!$par->getAttribute('class')) {
                        $program['descr'] = $par->textContent;
                    }
                }
            }
            $program['channel'] = $this->currentChannel;
            array_push($programs, $program);
            if ($limit && count($programs) == $limit) {
                break;
            }
        }

        return $programs;
    }

    /**
     *
     * @param $url
     * @return string|bool
     */
    public function getProgramInfo($url) {
        return $this->programParser->getProgramInfo($url);
    }

    /**
     * @param $page
     * @return bool|array
     */
    public function parseProgramData($page) {
        return $this->programParser->parseProgramData($page);
    }


    /**
     * @return array
     */
    public function getChannels() {
        return $this->channelParser->getChannels();
    }

    /**
     * @return array
     */
    public function getCategories() {
        return $this->channelParser->getCategories();
    }

}