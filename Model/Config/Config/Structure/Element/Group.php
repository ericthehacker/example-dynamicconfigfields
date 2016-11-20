<?php

namespace EW\DynamicConfigFields\Model\Config\Config\Structure\Element;

use \Magento\Config\Model\Config\Structure\Element\Group as OriginalGroup;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\CountryInformationInterface;
use \EW\DynamicConfigFields\Helper\Config as ConfigHelper;
use \Magento\Directory\Helper\Data as DirectoryHelper;

/**
 * Plugin to add dynamically generated store config fields
 * to General -> General -> State Options group.
 *
 * @package EW\DynamicConfigFields\Model\Config\Config\Structure\Element
 */
class Group
{
    /**
     * Config XML path of target group
     */
    const DIRECTORY_REGION_REQUIRED_GROUP_ID = 'region';

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
     * Get dynamic config fields (if any)
     *
     * @return array
     */
    protected function getDynamicConfigFields() : array {
        $countriesWithStatesRequired = $this->directoryHelper->getCountriesWithStatesRequired();

        $dynamicConfigFields = [];
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
                        'path' => ConfigHelper::ALLOWED_REGIONS_GROUP_PATH_PREFIX // Tab/section name: 'general/region'.
                    ];
                    break;
                case 'textarea':
                    $dynamicConfigFields[$configId] = [
                        'id' => $configId,
                        'type' => 'textarea',
                        'sortOrder' => ($index * 10),  // Generate unique and deterministic sortOrder values
                        'showInDefault' => '1',        // In this case, only show fields at default scope
                        'showInWebsite' => '0',
                        'showInStore' => '0',
                        'label' => __('Allowed Regions: %1', $countryInfo->getFullNameEnglish()),
                        'comment' => __(
                            'Enter allowed regions for %1, one per line.',
                            $countryInfo->getFullNameEnglish()
                        ),
                        '_elementType' => 'field',
                        'path' => ConfigHelper::ALLOWED_REGIONS_GROUP_PATH_PREFIX // Tab/section name: 'general/region'.
                    ];
                    break;
            }

        }

        return $dynamicConfigFields;
    }

    /**
     * Add dynamic region config fields for each country configured
     *
     * @param OriginalGroup $subject
     * @param callable $proceed
     * @param array $data
     * @param $scope
     * @return mixed
     */
    public function aroundSetData(OriginalGroup $subject, callable $proceed, array $data, $scope) {
        // This method runs for every group.
        // Add a condition to check for the one to which we're
        // interested in adding fields.
        if($data['id'] == self::DIRECTORY_REGION_REQUIRED_GROUP_ID) {
            $dynamicFields = $this->getDynamicConfigFields();

            if(!empty($dynamicFields)) {
                $data['children'] += $dynamicFields;
            }
        }

        return $proceed($data, $scope);
    }
}