<?php
class fco2astatusimport extends fco2abase {

    /**
     * Central execution method
     *
     * @param void
     * @return void
     */
    public function execute()
    {
        $blAllowed = $this->fcJobExecutionAllowed('statusimport');
        if (!$blAllowed) {
            echo "Execution of statusimport is not allowed by configuration\n";
            exit(1);
        }

        $aCheckOrderIds = $this->_fcGetNotFulfilledOrders();

        foreach ($aCheckOrderIds as $sOrderOxid) {
            // create afterbuy order status object
            $oOrder = oxNew('oxorder');
            $oOrder->load($sOrderOxid);
            $this->_fcProcessOrder($oOrder);
            $this->_fcSetLastCheckedDate($sOrderOxid);
        }
    }

    /**
     * Log possible state change
     *
     * @param $oAfterbuyOrder
     * @param $oOrder
     * @param $sStatetype
     * @param $blFlag
     */
    protected function _fcLogStateTypeOfOrder($oAfterbuyOrder, $oOrder, $sStatetype, $blFlag)
    {
        $sOrderIdAfterbuy = (string) $oAfterbuyOrder->OrderID;
        $sOrderIdOxid = $oOrder->oxorder__oxid->value;
        $sMessage =
            'MESSAGE: Order with ID (OXID:'.
            $sOrderIdOxid.
            '/AB:'.
            $sOrderIdAfterbuy.
            ') has updated '.
            $sStatetype.
            '?:'.
            (string) $blFlag;

        $this->oDefaultLogger->fcWriteLog($sMessage, 3);

    }

    /**
     * Requests API for given order and sets changed
     * values
     *
     * @param object &$oOrder
     * @return void
     */
    protected function _fcProcessOrder(&$oOrder)
    {
        $oAfterbuyApi = $this->_fcGetAfterbuyApi();

        $sAfterbuyOrderId = $oOrder->oxorder__fcafterbuy_aid->value;
        $sResponse = $oAfterbuyApi->getSoldItemsStatus($sAfterbuyOrderId);
        $oXmlResponse = simplexml_load_string($sResponse);
        $this->_fcParseApiResponse($oXmlResponse, $oOrder);
    }

    /**
     * Checks and parses API result
     *
     * @param object $oXmlResponse
     * @param object &$oOrder
     * @return void
     */
    protected function _fcParseApiResponse($oXmlResponse, &$oOrder)
    {
        if (!isset($oXmlResponse->Result->Orders->Order)) {
            $this->oApiLogger->fcWriteLog('ERROR: No valid Response from API while trying to fetch ab orderstatus. Content of Response is'.print_r($oXmlResponse,true),1);
            return;
        }

        foreach ($oXmlResponse->Result->Orders->Order as $oXmlOrder) {
            $this->oApiLogger->fcWriteLog("DEBUG: oXmlOrder:\n".print_r($oXmlOrder,true), 4);
            $oAfterbuyOrder = $this->_fcGetAfterbuyOrder();
            $oAfterbuyOrder->createOrderByApiResponse($oXmlOrder);
            $this->oApiLogger->fcWriteLog("DEBUG: Created result in oAfterbuyOrder:\n".print_r($oAfterbuyOrder,true), 4);
            $this->_fcUpdateOxidOrderStatus($oAfterbuyOrder, $oOrder);
        }
    }

    /**
     * Updates payment and shipping state, determines fulfillment of
     * external order and sets depending oxid order state
     *
     * @param object $oAfterbuyOrder
     * @param object &$oOrder
     * @return bool
     */
    protected function _fcUpdateOxidOrderStatus($oAfterbuyOrder, &$oOrder)
    {
        $blPaid = $this->_fcUpdatePaymentStatus($oAfterbuyOrder, $oOrder);
        $blShipped = $this->_fcUpdateShippingStatus($oAfterbuyOrder, $oOrder);

        if (strlen($oAfterbuyOrder->AdditionalInfo) > 0) {
            $oOrder->oxorder__oxtrackcode = new oxField($oAfterbuyOrder->AdditionalInfo);
        }

        $blFulFilled = ($blPaid && $blShipped);
        $blValuesUpdated = ($blPaid || $blShipped);

        $this->_fcLogStateTypeOfOrder(
            $oAfterbuyOrder,
            $oOrder,
            'fulfilled',
            $blFulFilled
        );

        $this->_fcLogStateTypeOfOrder(
            $oAfterbuyOrder,
            $oOrder,
            'values updated',
            $blValuesUpdated
        );


        if ($blFulFilled) {
            $oOrder->oxorder__fcafterbuy_fulfilledext = new oxField('1');
        }

        if ($blValuesUpdated) {
            $this->oDefaultLogger->fcWriteLog('DEBUG: UpdateOxidOrderStatus:'.print_r($oOrder, true), 4);
            $oOrder->save();
        }
    }

    /**
     * Updates payment date if set and depending on availibility
     * return that order is paid or not
     *
     * @param object $oAfterbuyOrder
     * @param object &$oOrder
     * @return bool
     * @todo: paid check also needs a string type and string format check
     */
    protected function _fcUpdatePaymentStatus($oAfterbuyOrder, &$oOrder)
    {
        $aPaymentInfo = (array) $oAfterbuyOrder->PaymentInfo;
        $sPaymentDate = $aPaymentInfo['PaymentDate'];
        $sOxidPaymentDate = $this->_fcGetDbDateTime($sPaymentDate);

        $blPaid = (
            $sOxidPaymentDate != '0000-00-00 00:00'
        );

        if ($blPaid) {
            $oOrder->oxorder__oxpaid = new oxField($sOxidPaymentDate);
        }

        $this->_fcLogStateTypeOfOrder(
            $oAfterbuyOrder,
            $oOrder,
            'paid date',
            $blPaid
        );

        return $blPaid;
    }

    /**
     * Updates shipping date if set and depending on availibility
     * return that order is shipped or not
     *
     * @param object $oAfterbuyOrder
     * @param object &$oOrder
     * @return bool
     */
    protected function _fcUpdateShippingStatus($oAfterbuyOrder, &$oOrder)
    {
        $aShippingInfo = (array) $oAfterbuyOrder->ShippingInfo;
        $sShippingDate = $aShippingInfo['DeliveryDate'];
        $sOxidShippingDate = $this->_fcGetDbDateTime($sShippingDate);

        $blShipped = (
            $sOxidShippingDate != '0000-00-00 00:00'
        );

        if ($blShipped) {
            $oOrder->oxorder__oxsenddate = new oxField($sOxidShippingDate);
        }

        $this->_fcLogStateTypeOfOrder(
            $oAfterbuyOrder,
            $oOrder,
            'shipping date',
            $blShipped
        );

        return $blShipped;
    }

    /**
     * Method fetches orders from oxid that are currnently not
     * fulfilled
     *
     * @param void
     * @return array
     */
    protected function _fcGetNotFulfilledOrders() {
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
                ooab.FCAFTERBUY_FULFILLEDEXT != '1'
        ";
        $aRows = $oDb->getAll($sQuery);

        foreach ($aRows as $aRow) {
            $aAffectedOrderIds[] = $aRow['OXID'];
        }

        return $aAffectedOrderIds;
    }
}
