<?php
/**
 * Created by PhpStorm.
 * User: mdoellerer-fc
 * Date: 13.07.20
 */

class fco2astockimport extends fco2abaseimport
{
    /**
     * Number ox maximum pages that will be processed
     * @var int
     */
    protected $_iMaxPages = 500;

    /**
     * List of variation ids and their correspending parentid
     * @var array
     */
    protected $_aVariations = array();

    /**
     * Central entry point for triggering product import
     *
     * @param void
     * @return void
     */
    public function execute() {
        $blAllowed = $this->fcJobExecutionAllowed('stockimport');
        if (!$blAllowed) {
            echo "Execution of stockimport is not allowed by configuration\n";
            exit(1);
        }
        $this->_fcProcessStocks();

    }

    private function _fcProcessStocks(){
        $this->oApiLogger->fcWriteLog(
            "DEBUG: Initiate process to Stock Update: \n", 1);

        $oAfterbuyApi = $this->_fcGetAfterbuyApi();
        $iPage = 1;
        while($iPage > 0 && $iPage <= $this->_iMaxPages) {
            $sResponse =
                $oAfterbuyApi->getShopProductsStocksFromAfterbuy($iPage);
            $oXmlResponse =
                simplexml_load_string($sResponse, null, LIBXML_NOCDATA);
            $iPage =
                $this->_fcParseApiStockResponse($oXmlResponse);
        }

        $this->oApiLogger->fcWriteLog(
            "DEBUG: Successfully finished the Stock Update: \n", 1);
    }

    private function _fcParseApiStockResponse($oXmlResponse){
        $iPage = $this->_fcGetNextPage($oXmlResponse);

        $aProducts = (array) $oXmlResponse->Result->Products;

        foreach ($aProducts['Product'] as $oXmlProduct) {
            if($this->_fcCheckIfArticleNumberIsValid($oXmlProduct) == false) {
                continue;
            }
            $this->_fcUpdateStocksInOxid($oXmlProduct);
        }

        return $iPage;
    }

    private function _fcUpdateStocksInOxid($oXmlProduct){

        $this->oApiLogger->fcWriteLog(
            "DEBUG: Trying to update stocks XML Product: \n".
            print_r($oXmlProduct ,true), 4);

        $sOxid = $this->_fcProductExists($oXmlProduct);

        if ($sOxid) {
            $oArticle = oxNew('oxarticle');
            $oArticle->fcAddCustomFieldsToObject();
            $oArticle->load($sOxid);
            $this->_fcAddProductAmounts($oXmlProduct, $oArticle);
            $oArticle->save();
        }
    }
}