<?php

namespace Yama\CuacaBmkg;

use Symfony\Component\DomCrawler\Crawler;

class CuacaBmkg
{
    protected $provinsi;
    protected $weather;

    protected $issue;

    protected $area;

    public function __construct()
    {
        $this->provinsi = $this->getProvinsi();
    }

    public function get($prop)
    {
        return $this->$prop;
    }

    protected function getProvinsi()
    {
        $url = "https://data.bmkg.go.id/prakiraan-cuaca/";
        $data = file_get_contents($url);
        $crawler = new Crawler($data);
        $provinsi = $crawler->filter('table.table.table-striped > tbody > tr');

        $dataProvinsi = [
            "meta" => [
                "copyright" => "BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)",
                "website" => "https://data.bmkg.go.id",
            ],
            "data" => []
        ];

        foreach ($provinsi as $prov) {
            $crawler = new Crawler($prov);
            $namaProvinsi = $crawler->filter('td')->eq(1)->text();
            $idProvinsi = $crawler->filter('td')->eq(2)->text();
            $idProvinsi = str_replace('DigitalForecast-', '', $idProvinsi);
            $idProvinsi = str_replace('.xml', '', $idProvinsi);
            $dataProvinsi['data'][] = [
                'nama' => $namaProvinsi,
                'id' => $idProvinsi
            ];
        }

        return $dataProvinsi;
    }

    public function weather($idProvinsi): void
    {
        $url = "https://data.bmkg.go.id/DataMKG/MEWS/DigitalForecast/DigitalForecast-" . $idProvinsi . ".xml";
        $data = file_get_contents($url);
        $xml = simplexml_load_string($data);
        $dataCuaca = [
            "meta" => [
                "copyright" => "BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)",
                "website" => "https://data.bmkg.go.id",
            ],
        ];
        $dataCuaca = array_merge($dataCuaca, xmlToArray($xml));
        $this->weather = $dataCuaca;
        $this->dataParser();
    }

    protected function dataParser()
    {
        $data = $this->weather;

        $issue = $data['data']['forecast']['issue'];

        $issue['timestamp'] = strtotime($issue['timestamp']);

        $this->issue = $issue;

        $area = $data['data']['forecast']['area'];

        $areaData = [];

        $kodeCuaca = [
            0 => "Cerah/Clear Skies",
            1 => "Cerah Berawan/Partly Cloudy",
            2 => "Cerah Berawan/Partly Cloudy",
            3 => "Berawan/Mostly Cloudy",
            4 => "Berawan Tebal/Overcast",
            5 => "Udara Kabur/Haze",
            10 => "Asap/Smoke",
            45 => "Kabut/Fog",
            60 => "Hujan Ringan/Light Rain",
            61 => "Hujan Sedang/Rain",
            63 => "Hujan Lebat/Heavy Rain",
            80 => "Hujan Lokal/Isolated Shower",
            95 => "Hujan Petir/Severe Thunderstorm",
            97 => "Hujan Petir/Severe Thunderstorm",
        ];

        foreach ($area as $a) {
            $temp = $a;

            $temp['name_alt'] = $a['name'][1];
            $temp['name'] = $a['name'][0];

            $temp['day'] = [];

            foreach ($temp['parameter'] as $key => $val) {
                $i = 0;
                foreach ($val['timerange'] as $k => $v) {
                    $timestamp = strtotime($v['datetime']);
                    $date = date('Ymd', $timestamp);
                    $temp['day'][$date]['date'] = date('Y-m-d', $timestamp);
                    $temp['day'][$date]['parameter'][$val['id']]['id'] = $val['id'];
                    $temp['day'][$date]['parameter'][$val['id']]['description'] = $val['description'];
                    $temp['day'][$date]['parameter'][$val['id']]['type'] = $val['type'];
                    $type = $v['type'] == 'daily' ? 'day' : 'h';
                    $temp['day'][$date]['parameter'][$val['id']]['time'][$i] = [
                        // 'type' => $v['type'],
                        // $type => $v[$type],
                        'timestamp' => $timestamp,
                        'value' => $v['value'],
                    ];
                    if ($val['id'] == 'weather') {
                        $temp['day'][$date]['parameter'][$val['id']]['time'][$i]['value']['description'] = $kodeCuaca[$v['value']['text']];
                    }
                    $i++;
                }
            }

            $temp['day'] = array_values($temp['day']);

            unset($temp['parameter']);

            $areaData[] = $temp;
        }

        $this->area = $areaData;
    }

    public function getAreaByName($name)
    {
        $data = $this->area;
        $filtered = array_filter($data, function ($val) use ($name) {
            return like($name, $val['name']);
        });

        return $filtered;
    }
}

function xmlToArray($xml, $options = array())
{
    $defaults = array(
        'namespaceSeparator' => ':', //you may want this to be something other than a colon
        'attributePrefix' => '',   //to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(),   //array of xml tag names which should always become arrays
        'autoArray' => true,        //only create arrays for tags which appear more than once
        'textContent' => 'text',       //key used for the text content of elements
        'autoText' => true,         //skip textContent key if node has no attributes or child nodes
        'keySearch' => false,       //optional search and replace on tag and attribute names
        'keyReplace' => false       //replace values for above search values (as passed to str_replace())
    );
    $options = array_merge($defaults, $options);
    $namespaces = $xml->getDocNamespaces();
    $namespaces[''] = null; //add base (empty) namespace

    //get attributes from all namespaces
    $attributesArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
            //replace characters in attribute name
            if ($options['keySearch']) $attributeName =
                str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
            $attributeKey = $options['attributePrefix']
                . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                . $attributeName;
            $attributesArray[$attributeKey] = (string)$attribute;
        }
    }

    //get child nodes from all namespaces
    $tagsArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->children($namespace) as $childXml) {
            //recurse into child nodes
            $childArray = xmlToArray($childXml, $options);
            // list($childTagName, $childProperties) = each($childArray);
            $childTagName = [];
            $childProperties = [];
            foreach ($childArray as $key => $value) {
                $childTagName = $key;
                $childProperties = $value;
            }

            //replace characters in tag name
            if ($options['keySearch']) $childTagName =
                str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
            //add namespace prefix, if any
            if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

            if (!isset($tagsArray[$childTagName])) {
                //only entry with this key
                //test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                    in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                    ? array($childProperties) : $childProperties;
            } elseif (
                is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                === range(0, count($tagsArray[$childTagName]) - 1)
            ) {
                //key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                //key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
            }
        }
    }

    //get text content of node
    $textContentArray = array();
    $plainText = trim((string)$xml);
    if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

    //stick it all together
    $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
        ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

    //return node as array
    return array(
        $xml->getName() => $propertiesArray
    );
}

function like($pattern, $subject)
{
    // Escape special regex characters
    $pattern = preg_quote($pattern, '#');

    // Replace SQL wildcards with regex wildcards
    $pattern = str_replace(['%', '_'], ['.*?', '.'], $pattern);

    // Surround the pattern with regex delimiters
    $pattern = "#^{$pattern}$#i";

    // Perform the regex match
    return (bool) preg_match($pattern, $subject);
}
