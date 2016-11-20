<?php

namespace EW\DynamicConfigFields\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;

class Config extends AbstractHelper
{
    const ALLOWED_REGIONS_GROUP_PATH_PREFIX = 'general/region';
    const ALLOWED_REGIONS_CONFIG_PATH_PREFIX = 'regions-allowed-';

    const ALLOWED_REGIONS_TAB_ID = 'general';
    const ALLOWED_REGIONS_SECTION_CONFIG_PATH_PREFIX = 'allowed-states-section-';

    /**
     * Get configured allowed regions from dynamic fields by country code
     *
     * @param string $countryCode
     * @param string $scopeType
     * @param null $scopeCode
     * @return array
     */
    public function getAllowedRegionsByDynamicField(
        string $countryCode,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) : array {
        $configPath = implode(
            '/',
            [
                self::ALLOWED_REGIONS_GROUP_PATH_PREFIX,
                self::ALLOWED_REGIONS_CONFIG_PATH_PREFIX . $countryCode
            ]
        );

        $rawValue = $this->scopeConfig->getValue($configPath, $scopeType, $scopeCode);

        // Split on either comma or newline to accommodate both multiselect
        // and textarea field types.
        $parsedValues = preg_split('/[,\n]/', $rawValue);

        return $parsedValues;
    }

    /**
     * Get configured allowed regions from fields in dynamic groups
     *
     * @param string $countryCode
     * @param string $scopeType
     * @param null $scopeCode
     * @return array
     */
    public function getAllowedRegionsByDynamicGroup(
        string $countryCode,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) : array {
        $configPath = implode(
            '/',
            [
                self::ALLOWED_REGIONS_TAB_ID,
                self::ALLOWED_REGIONS_SECTION_CONFIG_PATH_PREFIX . $countryCode,
                self::ALLOWED_REGIONS_CONFIG_PATH_PREFIX . $countryCode
            ]
        );

        $rawValue = $this->scopeConfig->getValue($configPath, $scopeType, $scopeCode);

        // Split on either comma or newline to accommodate both multiselect
        // and textarea field types.
        $parsedValues = preg_split('/[,\n]/', $rawValue);

        return $parsedValues;
    }
}