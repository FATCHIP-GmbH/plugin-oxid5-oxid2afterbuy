<?php


class fco2abaseimport extends fco2abase
{

    /**
     * Returns next page. If no next page available return zero
     *
     * @param $oXmlResponse
     * @return int
     */
    protected function _fcGetNextPage($oXmlResponse)
    {
        $iAvailablePages =
            (int) $oXmlResponse->Result->PaginationResult->TotalNumberOfPages;

        $iCurrentPage =
            (int) $oXmlResponse->Result->PaginationResult->PageNumber;

        $iNextPage =
            ($iCurrentPage<$iAvailablePages) ? ++$iCurrentPage : 0;

        return $iNextPage;
    }


    /**
     * declare articles as invalid if article number does not match the configurated conditions
     *
     * @param $oXmlProduct
     * @return bool
     */
    protected function _fcCheckIfArticleNumberIsValid($oXmlProduct) {
        $blDiscard = $this->getConfig()->getConfigParam('blFcAfterbuyIgnoreArticlesWithoutNr');

        if($blDiscard != true) {
            return true;
        }

        $sArtNum = $this->_fcGetArticleNumber($oXmlProduct);

        if(empty($sArtNum) || $sArtNum == 0) {
            $this->oDefaultLogger->fcWriteLog(
                "INFO: Product has been discarded because of missing article number \n".
                print_r($oXmlProduct ,true), 2);
            return false;
        }

        return true;
    }

    /**
     * Assign article number based on given config
     *
     * @param $oXmlProduct
     * @return string
     */
    protected function _fcGetArticleNumber($oXmlProduct) {
        $sSource = $this->getConfig()->getConfigParam('sFcAfterbuyImportArticleNumber');

        switch($sSource) {
            case '0': $sArtNum = $oXmlProduct->EAN ?: $oXmlProduct->Anr;
                break;
            case '1': $sArtNum = $oXmlProduct->EAN;
                break;
            case '2': $sArtNum = $oXmlProduct->ProductID;
                break;
            case '3': $sArtNum = $oXmlProduct->Anr;
                break;
            default: $sArtNum = $oXmlProduct->EAN ?: $oXmlProduct->Anr;
        }

        return $sArtNum;
    }

    /**
     * Returns oxid of product if exists or false if not
     *
     * @param $oXmlProduct
     * @return mixed
     */
    protected function _fcProductExists($oXmlProduct)
    {
        $sProductId = (int) $oXmlProduct->ProductID;

        $oDb = oxDb::getDb();

        $sQuery = "
            SELECT 
                OXID 
            FROM 
                oxarticles_afterbuy
            WHERE 
                fcafterbuyid = ".$oDb->quote($sProductId);

        $mOxid = $oDb->getOne($sQuery);

        return $mOxid;
    }


    /**
     * Adds identification data to oxid product
     *
     * @param object $oXmlProduct
     * @param object $oArticle
     */
    protected function _fcAddProductAmounts($oXmlProduct, &$oArticle)
    {
        $oArticle->oxarticles__oxstock =
            new oxField((int) $oXmlProduct->Quantity);
        $oArticle->oxarticles__oxunitname =
            new oxField((string) $oXmlProduct->UnitOfQuantity);
        $oArticle->oxarticles__oxunitquantity =
            new oxField((int) $oXmlProduct->BasepriceFactor);
        $oArticle->oxarticles__oxweight =
            new oxField((int) $oXmlProduct->Weight);

    }

}