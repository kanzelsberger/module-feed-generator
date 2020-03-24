<?php
/**
 * WaPoNe
 *
 * @category   WaPoNe
 * @package    WaPoNe_FeedGenerator
 * @copyright  Copyright (c) 2020 WaPoNe (https://www.fantetti.net)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace WaPoNe\FeedGenerator\Model;

use \Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\Filesystem\Io\File;
use \Magento\Framework\Exception\FileSystemException;

/**
 * Class FeedFile
 * @package WaPoNe\FeedGenerator\Model
 */
class FeedFile
{
    const FEED_FILE_NAME = 'feed';
    const FEED_FILE_TYPE_CSV = 'csv';
    const FEED_FILE_TYPE_XML = 'xml';
    const FEED_FILE_TYPE_HEUREKA = 'xmlh';
    const FIELD_SEPARATOR = '|';

    protected $_finalFeedFile;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $_directoryList;
    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $_io;

    /**
     * FeedFile constructor.
     *
     * @param DirectoryList $directoryList
     * @param File $io
     */
    public function __construct(
        DirectoryList $directoryList,
        File $io
    )
    {
        $this->_directoryList = $directoryList;
        $this->_io = $io;
    }

    /**
     * Write the feed file
     *
     * @param $feedDirectory
     * @param $feedType
     * @param $feedProducts
     * @return array
     */
    public function writeFeedFile($feedDirectory, $feedType, $feedProducts)
    {
        // directory check and creation
        try {
            // Feed Directory
            $feedDir = $this->_directoryList->getPath(DirectoryList::ROOT) . DIRECTORY_SEPARATOR . $feedDirectory . DIRECTORY_SEPARATOR;
            // Feed File
            $feedFilename = self::FEED_FILE_NAME . '.' . $feedType;
            // Feed Complete File
            $feedCompleteFilename = $feedDir . $feedFilename;

            $this->_io->checkAndCreateFolder($feedDir, 0775);
            // opening file in writable mode
            $this->_finalFeedFile = fopen($feedCompleteFilename, 'w');
            if ($this->_finalFeedFile === false) {
                return array("success" => false, "message" => "Opening file $feedCompleteFilename error!");
            }
        } catch (FileSystemException $exception) {
            return array("success" => false, "message" => "Directory $feedDir creation error: " . $exception->getMessage());
        } catch (\Exception $exception)  {
            return array("success" => false, "message" => "Directory check and creation error: " . $exception->getMessage());
        }

        switch ($feedType) {
            case self::FEED_FILE_TYPE_CSV:
                $ret = $this->_writeCSVFeedFile($feedProducts);
                break;
            case self::FEED_FILE_TYPE_XML:
                $ret = $this->_writeXMLFeedFile($feedProducts);
                break;
            case self::FEED_FILE_TYPE_HEUREKA:
                $ret = $this->_writeHeurekaFeedFile($feedProducts);
                break;
            default:
                $ret = $this->_writeCSVFeedFile($feedProducts);
        }

        // closing file
        fclose($this->_finalFeedFile);

        if (!$ret["success"]) {
            return $ret;
        }

        return array("success" => true);
    }

    /**
     * Writing feed file in CSV format
     *
     * @param $feedProducts
     * @return array
     */
    private function _writeCSVFeedFile($feedProducts)
    {
        try {
            foreach ($feedProducts as $feedProduct)
            {
                fwrite($this->_finalFeedFile, $feedProduct->getSku() . self::FIELD_SEPARATOR);
                fwrite($this->_finalFeedFile,$feedProduct->getName() . self::FIELD_SEPARATOR);
                fwrite($this->_finalFeedFile,$feedProduct->getPrice() . "\n");
            }
        } catch (\Exception $exception)  {
            return array("success" => false, "message" => "CSV Feed File creation error: " . $exception->getMessage());
        }

        return array("success" => true);
    }

    /**
     * Writing feed file in XML format
     *
     * @param $feedProducts
     * @return array
     */
    private function _writeXMLFeedFile($feedProducts)
    {
        try {
            fwrite($this->_finalFeedFile, "<Products>\n");

            foreach ($feedProducts as $feedProduct)
            {
                fwrite($this->_finalFeedFile, "<Product>\n");

                fwrite($this->_finalFeedFile,"<sku>" . $feedProduct->getSku() . "</sku>\n");
                fwrite($this->_finalFeedFile,"<name>" . $feedProduct->getName() . "</name>\n");
                fwrite($this->_finalFeedFile,"<price>" . $feedProduct->getPrice() . "</price>\n");

                fwrite($this->_finalFeedFile, "</Product>\n");
            }

            fwrite($this->_finalFeedFile, "</Products>");
        } catch (\Exception $exception)  {
            return array("success" => false, "message" => "XML Feed File creation error: " . $exception->getMessage());
        }

        return array("success" => true);
    }

    /**
     * Writing feed file in Heureka XML format
     *
     * @param $feedProducts
     * @return array
     */
    private function _writeHeurekaFeedFile($feedProducts)
    {
        $baseUrl = "https://threed.store";

        try {
            fwrite($this->_finalFeedFile, "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n");
            fwrite($this->_finalFeedFile, "<SHOP>\n");

            foreach ($feedProducts as $feedProduct)
            {
                $price = $feedProduct->getFinalPrice();
                if ($price > 0) {
                    $cpc = $price * 0.025;
                    if ($cpc > 1) {
                        $cpc = 1;
                    }

                    $description = $feedProduct->getData('short_description');
                    $descfiltered = strip_tags(str_replace('{{', '<', str_replace('}}', '>', $description)));

                    $manufacturer = $feedProduct->getAttributeText('manufacturer');
                    $color = $feedProduct->getAttributeText('color');
                    $heureka_name = $feedProduct->getData('heureka_name');
                    if ($heureka_name == "") {
                        $heureka_name = $feedProduct->getName();
                    }
                    $heureka_category = $feedProduct->getData('heureka_category');

                    fwrite($this->_finalFeedFile, "<SHOPITEM>\n");

                    fwrite($this->_finalFeedFile," <ITEM_ID>" . $feedProduct->getSku() . "</ITEM_ID>\n");
                    fwrite($this->_finalFeedFile," <PRODUCTNAME>" . $heureka_name . "</PRODUCTNAME>\n");
                    fwrite($this->_finalFeedFile," <PRODUCT>" . $feedProduct->getName() . "</PRODUCT>\n");
                    fwrite($this->_finalFeedFile," <DESCRIPTION><![CDATA[" . $descfiltered . "]]></DESCRIPTION>\n");
                    fwrite($this->_finalFeedFile," <CATEGORYTEXT>" . $heureka_category . "</CATEGORYTEXT>\n");
                    fwrite($this->_finalFeedFile," <PRICE_VAT>" . $feedProduct->getFinalPrice() * 1.2 . "</PRICE_VAT>\n");
                    fwrite($this->_finalFeedFile," <VAT>20%</VAT>\n");
                    fwrite($this->_finalFeedFile," <URL>" . $baseUrl . $feedProduct->getProductUrl() . "</URL>\n");
                    fwrite($this->_finalFeedFile," <IMGURL>" . $baseUrl . $feedProduct->getData('image') . "</IMGURL>\n");
                    fwrite($this->_finalFeedFile," <EAN>" . $feedProduct->getData('ts_hs_code') . "</EAN>\n");
                    fwrite($this->_finalFeedFile," <HEUREKA_CPC>" .$cpc . "</HEUREKA_CPC>\n");
                    fwrite($this->_finalFeedFile," <DELIVERY_DATE>" . "2" . "</DELIVERY_DATE>\n");
                    fwrite($this->_finalFeedFile," <DELIVERY>\n");
                    fwrite($this->_finalFeedFile,"  <DELIVERY_ID>DPD Classic</DELIVERY_ID>\n");
                    fwrite($this->_finalFeedFile,"  <DELIVERY_PRICE>3.50</DELIVERY_PRICE>\n");
                    fwrite($this->_finalFeedFile,"  <DELIVERY_PRICE_COD>4.30</DELIVERY_PRICE_COD>\n");
                    fwrite($this->_finalFeedFile," </DELIVERY>\n");

                    if ($manufacturer != "") {
                        fwrite($this->_finalFeedFile," <MANUFACTURER>" . $manufacturer . "</MANUFACTURER>\n");
                    }
                    if ($color != "") {
                        fwrite($this->_finalFeedFile," <PARAM>\n");
                        fwrite($this->_finalFeedFile,"  <PARAM_NAME>Farba</PARAM_NAME>\n");
                        fwrite($this->_finalFeedFile,"  <VALUE>" . $color . "</VALUE>\n");
                        fwrite($this->_finalFeedFile," </PARAM>\n");
                    }

                    fwrite($this->_finalFeedFile, "</SHOPITEM>\n");
                }
            }

            fwrite($this->_finalFeedFile, "</SHOP>");
        } catch (\Exception $exception)  {
            return array("success" => false, "message" => "XML Feed File creation error: " . $exception->getMessage());
        }

        return array("success" => true);
    }

}
