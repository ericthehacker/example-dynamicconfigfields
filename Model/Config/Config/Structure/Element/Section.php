<?php

namespace EW\DynamicConfigFields\Model\Config\Config\Structure\Element;

use \Magento\Config\Model\Config\Structure\Element\Section as OriginalSection;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\CountryInformationInterface;
use \EW\DynamicConfigFields\Helper\Config as ConfigHelper;
use \Magento\Directory\Helper\Data as DirectoryHelper;

/**
 * Plugin to add dynamically generated groups to
 * General -> General tab.
 *
 * @package EW\DynamicConfigFields\Model\Config\Config\Structure\Element
 */
class Section
{
    /**
     * Config path of target tab
     */
    const CONFIG_GENERAL_TAB_ID = 'general';

    /**
     * @var \Magento\Directory\Helper\Data
     */
    protected $directoryHelper;
    /**
     * @var CountryInformationAcquirerInterface
     */
    protected $countryInformationAcquirer;

    /**
     * Group constructor.
     * @param DirectoryHelper $directoryHelper
     * @param CountryInformationAcquirerInterface $countryInformationAcquirer
     */
    public function __construct(
        DirectoryHelper $directoryHelper,
        CountryInformationAcquirerInterface $countryInformationAcquirer
    )
    {
        $this->directoryHelper = $directoryHelper;
        $this->countryInformationAcquirer = $countryInformationAcquirer;
    }

    /**
     * Get config options array of regions for given country
     *
     * @param CountryInformationInterface $countryInfo
     * @return array
     */
    protected function getRegionsForCountry(CountryInformationInterface $countryInfo) : array {
        $options = [];

        $availableRegions = $countryInfo->getAvailableRegions() ?: [];

        foreach($availableRegions as $region) {
            $options[$region->getCode()] = [
                'value' => $region->getCode(),
                'label' => $region->getName()
            ];
        }

        return $options;
    }

    /**
     * Get dynamic config sections (if any)
     *
     * @return array
     */
    protected function getDynamicConfigSections() : array {
        $countriesWithStatesRequired = $this->directoryHelper->getCountriesWithStatesRequired();

        $dynamicConfigSections = [];
        foreach($countriesWithStatesRequired as $index => $country) {
            // Use a consistent prefix for dynamically generated fields
            // to allow them to be deterministic but not collide with any
            // preexisting fields.
            // ConfigHelper::ALLOWED_REGIONS_CONFIG_PATH_PREFIX == 'regions-allowed-'.
            $configId = ConfigHelper::ALLOWED_REGIONS_CONFIG_PATH_PREFIX . $country;

            $countryInfo = $this->countryInformationAcquirer->getCountryInfo($country);
            $regionOptions = $this->getRegionsForCountry($countryInfo);

            // Use type multiselect if fixed list of regions; otherwise, use textarea.
            $configType = !empty($regionOptions) ? 'multiselect' : 'textarea';

            $dynamicConfigFields = [];
            switch($configType) {
                case 'multiselect':
                    $dynamicConfigFields[$configId] = [
                        'id' => $configId,
                        'type' => 'multiselect',
                        'sortOrder' => ($index * 10), // Generate unique and deterministic sortOrder values
                        'showInDefault' => '1',       // In this case, only show fields at default scope
                        'showInWebsite' => '0',
                        'showInStore' => '0',
                        'label' => __('Allowed Regions: %1', $countryInfo->getFullNameEnglish()),
                        'options' => [                // Since this is a multiselect, generate options dynamically.
                            'option' => $this->getRegionsForCountry($countryInfo)
                        ],
                        'comment' => __(
                            'Select allowed regions for %1.',
                            $countryInfo->getFullNameEnglish()
                        ),
                        '_elementType' => 'field',
                        'path' => implode(            // Compute group path from tab ID and dynamic group ID
                            '/',
                            [
                                self::CONFIG_GENERAL_TAB_ID,
                                ConfigHelper::ALLOWED_REGIONS_SECTION_CONFIG_PATH_PREFIX . $country
                            ]
                        )
                    ];
                    break;
                case 'textarea':
                    $dynamicConfigFields[$configId] = [
                        'id' => $configId,
                        'type' => 'textarea',
                        'sortOrder' => ($index * 10), // Generate unique and deterministic sortOrder values
                        'showInDefault' => '1',       // In this case, only show fields at default scope
                        'showInWebsite' => '0',
                        'showInStore' => '0',
                        'label' => __('Allowed Regions: %1', $countryInfo->getFullNameEnglish()),
                        'comment' => __(
                            'Enter allowed regions for %1, one per line.',
                            $countryInfo->getFullNameEnglish()
                        ),
                        '_elementType' => 'field',
                        'path' => implode(            // Compute group path from tab ID and dynamic group ID
                            '/',
                            [
                                self::CONFIG_GENERAL_TAB_ID,
                                ConfigHelper::ALLOWED_REGIONS_SECTION_CONFIG_PATH_PREFIX . $country
                            ]
                        )
                    ];
                    break;
            }

            $dynamicConfigSections[$country] = [    // Declare group information
                'id' => $country,                   // Use dynamic group ID
                'label' => __(
                    '%1 Allowed Regions',
                    $countryInfo->getFullNameEnglish()
                ),
                'showInDefault' => '1',             // Show in default scope
                'showInWebsite' => '0',             // Don't show in website scope
                'showInStore' => '0',               // Don't show in store scope
                'sortOrder' => ($index * 10),       // Generate unique and deterministic sortOrder values
                'children' => $dynamicConfigFields  // Use dynamic fields generated above
            ];
        }

        return $dynamicConfigSections;
    }

    /**
     * Add dynamic region config sections for each country configured
     *
     * @param OriginalSection $subject
     * @param callable $proceed
     * @param array $data
     * @param $scope
     * @return mixed
     */
    public function aroundSetData(OriginalSection $subject, callable $proceed, array $data, $scope) {
        // This method runs for every tab.
        // Add a condition to check for the one to which we're
        // interested in adding groups.
        if($data['id'] == self::CONFIG_GENERAL_TAB_ID) {
            $dynamicSections = $this->getDynamicConfigSections();

            if(!empty($dynamicSections)) {
                $data['children'] += $dynamicSections;
            }
        }

        return $proceed($data, $scope);
    }
}