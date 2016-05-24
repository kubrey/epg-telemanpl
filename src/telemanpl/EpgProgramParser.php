<?php

namespace telemanpl;


class EpgProgramParser extends BaseEpgParser
{
    /**
     * Programs array has enough information about each program
     * @unused
     * @param $url
     * @return string|bool
     */
    public function getProgramInfo($url) {
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
     * @unused
     * @param $page
     * @return bool|array
     */
    public function parseProgramData($page) {
        if (!$page) {
            $this->setError("No page content is set");
            return false;
        }
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = true;
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        $dom->encoding = 'UTF-8';

        $shortInfoId = "showMainInfo";
        $moreInfoId = "showMoreInfo";
        $commonId = "content";

        $program = array();

        $shortInfo = $dom->getElementById($shortInfoId);
        if ($shortInfo) {
            $shortData = $this->parseProgramShortInfo($shortInfo);
            if ($shortData) {
                foreach ($shortData as $field => $val) {
                    $program[$field] = $val;
                }
            }
        }
        $moreInfo = $dom->getElementById($moreInfoId);
        if ($moreInfo) {
            $moreData = $this->parseProgramMoreInfo($moreInfo);
            if ($moreData) {
                foreach ($moreData as $field => $val) {
                    $program[$field] = $val;
                }
            }
        }
        $commonInfo = $dom->getElementById($commonId);
        if ($commonInfo) {
            $commonData = $this->parseProgramCommonInfo($commonInfo);
            if ($commonData) {
                foreach ($commonData as $field => $val) {
                    $program[$field] = $val;
                }
            }
        }

        return $program;
    }

    /**
     * @param \DOMElement $data
     * @return bool|array
     */
    protected function parseProgramShortInfo($data) {
        $spans = $data->getElementsByTagName('span');
        if (!$spans || !$spans->length) {
            return false;
        }
        $info = array();
        $rating = 0;
        foreach ($spans as $span) {
            /**
             * @var \DOMElement $span
             */
            if ($span->getAttribute('class') == 'sep') {
                continue;
            }
            if (strpos($span->getAttribute('class'), 'age-rating') === 0) {
                $rating = preg_replace('/[^0-9]+/', '', $span->textContent);
            }
            $info[] = $span->textContent;
        }

        return array('genre' => strtolower(current($info)), 'rating' => $rating);
    }

    /**
     * @param \DOMElement $data
     * @return bool|array
     */
    protected function parseProgramMoreInfo($data) {
        if (!$data) {
            return false;
        }
        $rows = $data->getElementsByTagName('tr');
        if (!$rows || !$rows->length) {
            return false;
        }
        $info = array();

        $options = array('Występują:' => 'actors', 'Reżyseria:' => 'director', 'W skrócie:' => 'short_descr');
        foreach ($rows as $tr) {
            /**
             * @var \DOMElement $tr
             */
            $ths = $tr->getElementsByTagName('th');
            if (!$ths || !$ths->length) {
                continue;
            }
            $th = $ths->item(0);
            if (in_array($th->textContent, array_keys($options))) {
                //getting actors
                $tds = $tr->getElementsByTagName('td');
                if (!$tds || !$tds->length) {
                    continue;
                }
                $td = $tds->item(0);
                $info[$options[$th->textContent]] = $td->textContent;
            }
        }

        return $info;
    }

    /**
     * @param \DOMElement $data
     * @return bool|array
     */
    protected function parseProgramCommonInfo($data) {
        if (!$data) {
            return false;
        }
        $divs = $data->getElementsByTagName('div');
        if (!$divs || !$divs->length) {
            return false;
        }
        $info = array();
        foreach ($divs as $div) {
            /**
             * @var \DOMElement $div
             */
            if ($div->getAttribute('class') != 'section') {
                continue;
            }
            $paragraps = $div->getElementsByTagName('p');
            if (!$paragraps || !$paragraps->length) {
                continue;
            }
            /**
             * @var \DOMElement $p
             */
            $p = $paragraps->item(0);
            if ($p->getAttribute('itemprop') == 'description') {
                $info['description'] = $p->textContent;
            }
        }
        return $info;

    }
}