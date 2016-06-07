<?php

namespace telemanpl;




class EpgChannelParser extends BaseEpgParser
{

    protected $channelsPage = null;
    protected $categories = array();
    protected $channels = array();

    public function loadIndexPage() {
        $urlParts = parse_url($this->config['baseUrl']);
        $url = $urlParts['scheme'] . "://" . $urlParts['host'];
        $this->initCurl($url)->runCurl();
        if ($this->curlError) {
            $error = $this->curlError . "\n Url: " . $this->curlInfo['url'] . ";";
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

        return $this->curlResult;
    }

    /**
     * @param string $page html of index page
     * @return array|bool  array of channels each channel as [url=>'',name=>'']
     */
    public function parseIndexPage($page) {
        $id = "stationsIndex";
        if (!$page) {
            $this->setError("No index page content is set");
            return false;
        }
        $channels = array();
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = true;
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        $dom->encoding = 'UTF-8';
        $wrap = $dom->getElementById($id);
        if (!$wrap) {
            unset($dom);
            $this->setError("No stations div");
            return false;
        }
        $sections = $wrap->getElementsByTagName('ul');
        if (!$sections || !$sections->length) {
            unset($dom);
            $this->setError("No list found for stations");
            return false;
        }
        /**
         * @var \DOMNode $list
         */
        $list = $sections->item(0);

        $items = $list->childNodes;
        foreach ($items as $li) {
            /**
             * @var \DOMElement $li
             */
            $links = $li->getElementsByTagName('a');
            if (!$links || !$links->length) {
                continue;
            }
            foreach ($links as $link) {
                /**
                 * @var \DOMElement $link
                 */
                $url = $link->getAttribute('href');
                $name = $link->textContent;
                if (strpos($url, "/program-tv/") !== false) {
                    $channels[] = array('url' => $url, 'name' => $name);
                }
            }
        }
        return $channels;
    }

    /**
     * get channels page html
     * @return bool|null
     */
    public function loadChannels() {
        $this->initCurl($this->config['channelsUrl'])->runCurl();
        if ($this->curlError) {
            $error = $this->curlError . "\n Url: " . $this->curlInfo['url'] . ";";
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

        return true;

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
     * @param $channels
     * @return $this
     */
    public function setChannels($channels){
        $this->channels = $channels;
        return $this;
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