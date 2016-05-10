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
 * @package telemanpl
 */
class EpgParser extends BaseEpgParser
{
    protected $categories = array();
    protected $channels = array();
    protected $programs = array();

    protected $channelsPage = null;
    protected $currentChannel = null;
    protected $currentDay;


    public function loadChannels() {
        $this->initCurl($this->config['channelsUrl'])->runCurl();
        if ($this->curlError) {
            $this->setError($this->curlError);
            return false;
        }
        if ($this->curlInfo['http_code'] != '200') {
            $this->setError("http code is not OK or content is invalid " . $this->curlInfo['http_code'] . "/" . $this->curlInfo['content_type']);
            return false;
        }
        $this->channelsPage = $this->curlResult;
        return $this->channelsPage;
    }

    /**
     * @param $page
     * @return bool
     */
    public function parseChannels($page) {
        if (!$page) {
            $this->setError("No page content is set");
            return false;
        }
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = true;
        @$dom->loadHTML($page);
        $form = $dom->getElementById("user-stations-form");
        if (!$form) {
            unset($dom);
            $this->setError("No stations form");
            return false;
        }
        $sections = $form->getElementsByTagName('div');
        if (!$sections || !$sections->length) {
            unset($dom);
            $this->setError("No sections found for stations");
            return false;
        }
        foreach ($sections as $section) {
            /**
             * @var \DOMElement $section
             */
            if (strpos($section->getAttribute('class'), 'section') === false) {
                continue;
            }
            $categoryList = $section->getElementsByTagName('h2');
            if (!$categoryList || !$categoryList->length) {
                continue;
            }
            $category = $categoryList->item(0)->textContent;
            $this->categories[] = $category;
            $divContainer = $section->getElementsByTagName('div');
            if (!$divContainer || !$divContainer->length) {
                continue;
            }
            /**
             * @var \DOMElement $container
             */
            $container = $divContainer->item(0);
            $uls = $container->getElementsByTagName('ul');
            if (!$uls || !$uls->length) {
                continue;
            }
            $this->parseSectionUnsignedLists($uls, $category);

        }

        unset($dom);

    }

    /**
     * Parsing day's first program
     * @param string $day
     * @param string $channelName
     * @return bool
     */
    protected function parseDayFirstProgram($day, $channelName) {
        try {
            $dayObject = new \DateTime($day);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
        $this->currentChannel = $channelName;
        $channelName = str_replace(" ", "-", trim($channelName));

        $url = $this->config['baseUrl'] . $channelName . "?date=" . $dayObject->format('Y-m-d') . "&hour=-1";
        $this->initCurl($url)->runCurl();
        if ($this->curlError) {
            $this->setError($this->curlError);
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
        @$dom->loadHTML($page);
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
     * @param \DOMNodeList $lists
     * @param string $category
     * @return bool|array
     */
    protected function parseSectionUnsignedLists($lists, $category) {
        if (!$lists || !$lists->length) {
            return false;
        }
        $channels = array();
        foreach ($lists as $list) {
            /**
             * @var \DOMElement $list
             */
            $lis = $list->getElementsByTagName('li');
            if (!$lis || !$lis->length) {
                continue;
            }
            $channel = array();

            foreach ($lis as $li) {
                /**
                 * @var \DOMElement $li
                 */
                $spans = $li->getElementsByTagName('span');
                if (!$spans || !$spans->length) {
                    continue;
                }
                $channelName = $spans->item(0)->textContent;
                $inputs = $li->getElementsByTagName('input');
                if (!$inputs || !$inputs->length) {
                    continue;
                }
                $channelData = $inputs->item(0);
                /**
                 * @var \DOMElement $channelData
                 */
                $channelId = $channelData->getAttribute('value');
                $channel['id'] = $channelId;
                $channel['name'] = $channelName;
                $channel['category'] = $category;
                array_push($channels, $channel);
                array_push($this->channels, $channel);
            }
        }
        unset($lists);
        return $channels;
    }

    /**
     *
     * @param string $day as Y-m-d
     * @param string $channelName
     * @return array|boolean
     */
    public function loadDay($day, $channelName) {
        try {
            $dayObject = new \DateTime($day);
            $this->currentDay = $dayObject;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
        $this->currentChannel = $channelName;
        $channelName = str_replace(" ", "-", trim($channelName));

        $url = $this->config['baseUrl'] . $channelName . "?date=" . $dayObject->format('Y-m-d') . "&hour=-1";
        var_dump($url);
        $this->initCurl($url)->runCurl();
        if ($this->curlError) {
            $this->setError($this->curlError);
            return false;
        }
        if ($this->curlInfo['http_code'] != '200') {
            $this->setError("http code is not OK or content is invalid " . $this->curlInfo['http_code'] . "/" . $this->curlInfo['content_type']);
            return false;
        }
        return $this->curlResult;
    }

    /**
     * @param $page
     * @return bool|array
     */
    public function parseDaySchedule($page) {
        if (!$page) {
            $this->setError("No page content is set");
            return false;
        }
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = true;
        @$dom->loadHTML($page);
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
        foreach ($uls as $ul) {
            /**
             * @var \DOMElement $ul
             */
            if ($ul->getAttribute('class') == 'stationItems') {
                $programs = $this->parseStationItems($ul->getElementsByTagName('li'));
                if ($programs) {
                    foreach ($programs as $program) {
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
                    $nextProgram = $this->parseDayFirstProgram($next, $this->currentChannel);
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
     * Programs array has enough information about each program
     * @unused
     * @param $url
     */
    public function getProgramInfo($url) {

    }

    /**
     * @unused
     * @param $page
     */
    public function parseProgramData($page) {

    }

    /**
     * @return array
     */
    public function getChannels() {
        return $this->channels;
    }

    /**
     * @return array
     */
    public function getCategories() {
        return $this->categories;
    }

}