<?php
class fco2astatusexport extends fco2abase {

    /**
     * Central execution method
     *
     * @param void
     * @return void
     */
    public function execute()
    {
        $blAllowed = $this->fcJobExecutionAllowed('statusexport');
        if (!$blAllowed) {
            echo "Execution of statusexport is not allowed by configuration\n";
            exit(1);
        }

        $oAfterbuyApi = $this->_fcGetAfterbuyApi();

        // load order IDs of changed afterbuy orders to export from oxorder/oxorderarticles
        $aUpdateOrderIds = $this->_fcGetUpdatedAfterbuyOrders();

        // foreach order
        foreach ($aUpdateOrderIds as $sOrderOxid) {
            // create afterbuy order status object
            $oAfterbuyOrderStatus = $this->_fcGetAfterbuyStatus();
            $oOrder = oxNew('oxorder');
            $oOrder->load($sOrderOxid);
            $oAfterbuyOrderStatus =
                $this->_fcAssignOrderDataToOrderStatus($oOrder, $oAfterbuyOrderStatus);
            $sResponse =
                $oAfterbuyApi->updateSoldItemsOrderState($oAfterbuyOrderStatus);
            $blApiCallSuccess =
                $this->_fcCheckApiCallSuccess($sResponse);

            // mark orderstatus as fulfilled in OXID database if there is a remarkable event
            $blFulfilled = (
                $oOrder->oxorder__oxpaid->value != '0000-00-00 00:00:00' &&
                $oOrder->oxorder__oxsenddate->value != '0000-00-00 00:00:00' &&
                $blApiCallSuccess
            );
            if ($blFulfilled) {
                $oOrder->oxorder__fcafterbuy_fulfilled = new oxField(1);
            }
            $oOrder->save();
            $this->_fcSetLastCheckedDate($sOrderOxid);
        }
    }

    /**
     * Assign current order data
     *
     * @param $oOrder
     * @param $oAfterbuyOrderStatus
     * @return object
     */
    protected function _fcAssignOrderDataToOrderStatus($oOrder, $oAfterbuyOrderStatus) {
        $oAfterbuyOrderStatus->OrderID = $this->_fcFetchAfterbuyOrderId($oOrder);
        $sOrderSendDate = $oOrder->oxorder__oxsenddate->value;
        $sPaidDate = $oOrder->oxorder__oxpaid->value;
        $oAfterbuyOrderStatus->AdditionalInfo = $oOrder->oxorder__oxtrackcode->value;

        if ($sOrderSendDate != '0000-00-00 00:00:00') {
            $oShippingInfo = new stdClass();
            $oShippingInfo->DeliveryDate = $this->_fcGetGermanDate($sOrderSendDate);
            $oAfterbuyOrderStatus->ShippingInfo = $oShippingInfo;
        }
        if ($sPaidDate != '0000-00-00 00:00:00') {
            $oPaymentInfo = new stdClass();
            $oPaymentInfo->PaymentDate = $this->_fcGetGermanDate($sPaidDate);
            $oAfterbuyOrderStatus->PaymentInfo = $oPaymentInfo;
        }

        return $oAfterbuyOrderStatus;
    }

    /**
     * Extracts afterbuy orderid from order. This can be placed in AID or UID
     * field
     *
     * @param $oOrder
     * @return string
     */
    protected function _fcFetchAfterbuyOrderId($oOrder)
    {
        $sAId = (string) $oOrder->oxorder__fcafterbuy_aid->value;
        $sUId = (string) $oOrder->oxorder__fcafterbuy_uid->value;

        $sOrderID = ($sAId) ? $sAId : $sUId;

        return $sOrderID;
    }

    /**
     * Method determines changed afterbuy orders and returns a list of ids
     *
     * @param void
     * @return array
     */
    protected function _fcGetUpdatedAfterbuyOrders() {
        $aAffectedOrderIds = array();
        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);

        $sQuery = "
            SELECT 
                oo.OXID 
            FROM 
                oxorder oo
            LEFT JOIN 
                oxorder_afterbuy ooab ON (oo.OXID=ooab.OXID)
            WHERE 
                ooab.FCAFTERBUY_UID != '' 
            AND 
                oo.OXTIMESTAMP > ooab.FCAFTERBUY_LASTCHECKED 
            AND
                ooab.FCAFTERBUY_FULFILLED != '1'
        ";
        $aRows = $oDb->getAll($sQuery);

        foreach ($aRows as $aRow) {
            $aAffectedOrderIds[] = $aRow['OXID'];
        }

        return $aAffectedOrderIds;
    }

}
