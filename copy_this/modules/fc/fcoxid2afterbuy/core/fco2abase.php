<?php

/**
 * Created by PhpStorm.
 * User: andre
 * Date: 07.09.17
 * Time: 12:39
 */
class fco2abase extends oxBase {

    /**
     * @var string
     */
    protected $sAfterbuyShopInterfaceUrl = "https://api.afterbuy.de/afterbuy/ShopInterface.aspx";

    /**
     * @var string
     */
    protected $sAfterbuyShopInterfaceUTF8Url = "https://api.afterbuy.de/afterbuy/ShopInterfaceUTF8.aspx";

    /**
     * @var string
     */
    protected $sAfterbuyInterfaceUrl = "https://api.afterbuy.de/afterbuy/ABInterface.aspx";

    /**
     * Current loglevel
     * @var int
     */
    protected $_iFcLogLevel = null;

    /**
     * Logfile for standard output
     * @var string
     */
    protected $_sLogFile = 'fco2a_default.log';

    /**
     * Ident for oxid counter
     * @var string
     */
    protected $_sCounterIdent = 'fcAfterbuyLastOrder';

    /**
     * Instance of afterbuy api
     *
     * @var object
     */
    protected $_oAfterbuyApi = null;

    /**
     * Afterbuy settings
     *
     * @var array
     */
    protected $_aAfterbuyConfig = null;

    protected $_aAllowedExecution = array(
        '0' => array(
            'artexport',
            'orderimport',
            'statusexport',
        ),
        '1' => array(
            'artimport',
            'statusimport',
            'orderexport',
        ),
    );

    /**
     * fco2abase constructor.
     * initialize loglevel
     */
    public function __construct() {
        parent::__construct();
        $oConfig = $this->getConfig();
        $this->_iFcLogLevel = (int)$oConfig->getConfigParam('iFcAfterbuyLogLevel');
    }

    /**
     * Central logging method. Timestamp will be added automatically.
     * Logs only if loglevel matches
     *
     * @param string $sMessage
     * @param int $iLogLevel
     * @return void
     * @access protected
     */
    public function fcWriteLog($sMessage, $iLogLevel = 1) {
        $sTime = date("Y-m-d H:i:s");
        $sFullMessage = "[" . $sTime . "] " . $sMessage . "\n";
        if ($iLogLevel <= $this->_iFcLogLevel) {
            $oUtils = oxRegistry::getUtils();
            $oUtils->writeToLog($sFullMessage, $this->_sLogFile);
        }
    }

    /**
     * Checks if job execution is allowed by configuration (Leading System)
     *
     * @param $sJobIdent
     * @return bool
     */
    public function fcJobExecutionAllowed($sJobIdent) {
        $oConfig = $this->getConfig();

        $sLeadSystem =
            (string) $oConfig->getConfigParam('sFcAfterbuyLeadSystem');
        $aAllowedJobs = $this->_aAllowedExecution[$sLeadSystem];
        $blAllowed = (bool) in_array($sJobIdent, $aAllowedJobs);

        return $blAllowed;
    }

    /**
     * Returns needed configuration for instantiate afterbuy api object
     *
     * @param void
     * @return array
     */
    protected function _fcGetAfterbuyConfigArray() {
        if ($this->_aAfterbuyConfig === null) {
            $oConfig = $this->getConfig();
            $aConfig = array(
                'afterbuyShopInterfaceBaseUrl' => $this->_fcGetShopInterfaceBaseUrl(),
                'afterbuyAbiUrl' =>
                    $this->sAfterbuyInterfaceUrl,
                'afterbuyPartnerId' =>
                    $oConfig->getConfigParam('sFcAfterbuyPartnerId'),
                'afterbuyPartnerPassword' =>
                    $oConfig->getConfigParam('sFcAfterbuyPartnerPassword'),
                'afterbuyUsername' =>
                    $oConfig->getConfigParam('sFcAfterbuyUsername'),
                'afterbuyUserPassword' =>
                    $oConfig->getConfigParam('sFcAfterbuyUserPassword'),
                'logLevel' =>
                    $oConfig->getConfigParam('iFcAfterbuyLogLevel'),
                'lastOrderId' =>
                    $this->_fcGetLastOrderId(),
            );

            $this->_aAfterbuyConfig = $aConfig;
        }

        return $this->_aAfterbuyConfig;
    }


    /**
     * returns shopInterfaceBaseUrl depending on configuration
     *
     * @param $oConfig
     * @return string
     */
    protected function _fcGetShopInterfaceBaseUrl($oConfig) {
        if($oConfig->getConfigParam('blFcAfterbuyExportUTF8Orders') === true) {
            return $this->sAfterbuyShopInterfaceUTF8Url;
        }

        return $this->sAfterbuyShopInterfaceUrl;
    }

    /**
     * Returns current orderid from oxCounter
     *
     * @param void
     * @return string
     */
    protected function _fcGetLastOrderId() {
        $oCounter = oxNew('oxCounter');
        $sLastOrderId = $oCounter->fcGetCurrent($this->_sCounterIdent);

        return (string) $sLastOrderId;
    }

    /**
     * Returns german formatted date for offered different datetime format
     *
     * @param $sDateString
     * @return string
     */
    protected function _fcGetGermanDate($sDateString) {
        $sReturn =
            $this->_fcTransformDate($sDateString,'d.m.Y');

        return $sReturn;
    }

    /**
     * Returns given input date(-time)string in mysql
     * datetime format
     *
     * @param $sDateString
     * @return string
     */
    protected function _fcGetDbDateTime($sDateString) {
        $sReturn =
            $this->_fcTransformDate($sDateString,'Y-m-d H:i:s');

        return $sReturn;
    }

    /**
     * Transformes date
     *
     * @param $sDateString
     * @param $sPattern
     * @return string
     */
    protected function _fcTransformDate($sDateString, $sPattern, $blKeepDateStringOnError=true)
    {
        $iTime = strtotime($sDateString);
        $mReturn = '';
        if ($iTime) {
            $mReturn = date($sPattern, $iTime);
        }

        $blReturnIncoming = (
            $mReturn === false &&
            $blKeepDateStringOnError
        );

        $mReturn = ($blReturnIncoming) ? $sDateString: $mReturn;

        return $mReturn;
    }

    /**
     * Returns afterbuy api object
     *
     * @param void
     * @return object
     */
    protected function _fcGetAfterbuyApi() {
        if ($this->_oAfterbuyApi === null) {
            $aConfig = $this->_fcGetAfterbuyConfigArray();
            $oAfterbuyApi = oxNew("fcafterbuyapi",$aConfig);
            $oAfterbuyApi->setLogFilePath(getShopBasePath()."/log/fco2a_api.log");
            $this->_oAfterbuyApi = $oAfterbuyApi;
        }

        return $this->_oAfterbuyApi;
    }

    /**
     * Returns a new afterbuy order status object
     *
     * @param void
     * @return object
     */
    protected function _fcGetAfterbuyStatus() {
        $oAfterbuyStatus = oxNew('fcafterbuyorderstatus');
        return $oAfterbuyStatus;
    }

    /**
     * Returns a new afterbuy order object
     *
     * @param void
     * @return object
     */
    protected function _fcGetAfterbuyOrder() {
        $oAfterbuyOrder = oxNew("fcafterbuyorder");

        return $oAfterbuyOrder;
    }

    /**
     * Returns an afterbuy article object
     *
     * @param void
     * @return object fcafterbuyart
     */
    protected function _fcGetAfterbuyArticle() {
        $oAfterbuyArticle = oxNew("fcafterbuyart");

        $this->fcWriteLog(
            "DEBUG: Created Afterbuy Object:".
            print_r($oAfterbuyArticle,true),
            4
        );

        return $oAfterbuyArticle;
    }

    protected function _fcGetAfterbuyCatalog() {

    }

    /**
     * Rerturns true/false depending on response of an API-Call
     *
     * @param $sResponse
     * @return bool
     */
    protected function _fcCheckApiCallSuccess($sResponse) {
        $blReturn = false;
        if (strpos($sResponse, '<CallStatus>Success</CallStatus>') !== false) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Sets last check date of this order to now
     *
     * @param $sOrderOxid
     * @return void
     */
    protected function _fcSetLastCheckedDate($sOrderOxid) {
        $oDb = oxDb::getDb();
        $sQuery = "
            UPDATE 
                oxorder_afterbuy 
            SET 
                FCAFTERBUY_LASTCHECKED=NOW() 
            WHERE 
                OXID=".$oDb->quote($sOrderOxid)." LIMIT 1";
        $oDb->execute($sQuery);
    }


}
