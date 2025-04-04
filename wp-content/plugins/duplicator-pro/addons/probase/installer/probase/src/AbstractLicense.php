<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Addons\ProBase;

use Exception;

abstract class AbstractLicense
{
    const TYPE_UNKNOWN         = -1;
    const TYPE_UNLICENSED      = 0;
    const TYPE_PERSONAL        = 1;
    const TYPE_FREELANCER      = 2;
    const TYPE_BUSINESS        = 3;
    const TYPE_PERSONAL_AUTO   = 4;
    const TYPE_FREELANCER_AUTO = 5;
    const TYPE_BUSINESS_AUTO   = 6;
    const TYPE_GOLD            = 7;
    const TYPE_BASIC           = 8;
    const TYPE_PLUS            = 9;
    const TYPE_PRO             = 10;
    const TYPE_ELITE           = 11;

    const CAPABILITY_BRAND                   = 1000;
    const CAPABILITY_IMPORT_SETTINGS         = 1001;
    const CAPABILITY_SHEDULE_HOURLY          = 1002;
    const CAPABILITY_MULTISITE               = 1003;
    const CAPABILITY_MULTISITE_PLUS          = 1004;
    const CAPABILITY_POWER_TOOLS             = 1005;
    const CAPABILITY_CHANGE_TABLE_PREFIX     = 1006;
    const CAPABILITY_UPDATE_AUTH             = 1007;
    const CAPABILITY_CAPABILITIES_MNG        = 1008;
    const CAPABILITY_CAPABILITIES_MNG_PLUS   = 1009;
    const CAPABILITY_PRO_BASE                = 1010;
    const CAPABILITY_PACKAGE_COMPONENTS_PLUS = 1011;
    const CAPABILITY_IMPORT                  = 1012;
    const CAPABILITY_SCHEDULE                = 1013;
    const CAPABILITY_STORAGE                 = 1014;
    const CAPABILITY_TEMPLATE                = 1015;

    /**
     * Return true if multisite is enabled
     *
     * @return bool
     */
    protected static function isMultisite()
    {
        // This is to avoid warnings in PHP 5.6 because isn't possibile declare an abstract static method.
        throw new Exception('This method must be extended');
    }

    /**
     * Returns the license type this installer file is made of.
     *
     * @return int Returns an enum type of License
     */
    public static function getType()
    {
        // This is to avoid warnings in PHP 5.6 because isn't possibile declare an abstract static method.
        throw new Exception('This method must be extended');
    }

    /**
     * Return license limit
     *
     * @return int<0, max>
     */
    public static function getLimit()
    {
        // This is to avoid warnings in PHP 5.6 because isn't possibile declare an abstract static method.
        throw new Exception('This method must be extended');
    }

    /**
     * Return upsell URL
     *
     * @return string
     */
    public static function getUpsellURL()
    {
        // This is to avoid warnings in PHP 5.6 because isn't possibile declare an abstract static method.
        throw new Exception('This method must be extended');
    }

    /**
     * Return true if license is unlimited
     *
     * @return bool
     */
    public static function isUnlimited()
    {
        return in_array(
            static::getType(),
            [
                self::TYPE_BUSINESS,
                self::TYPE_BUSINESS_AUTO,
                self::TYPE_GOLD,
            ]
        );
    }

    /**
     * Return true if license have the capability
     *
     * @param int  $capability capability key
     * @param ?int $license    ENUM license type, if null Get currnt licnse type
     *
     * @return bool
     */
    public static function can($capability, $license = null)
    {
        if (is_null($license)) {
            $license = static::getType();
        }

        $basicMultisite = in_array(
            $license,
            [
                self::TYPE_PERSONAL,
                self::TYPE_FREELANCER,
                self::TYPE_FREELANCER_AUTO,
                self::TYPE_BUSINESS,
                self::TYPE_BUSINESS_AUTO,
                self::TYPE_GOLD,
                self::TYPE_PRO,
                self::TYPE_ELITE,
            ]
        );

        switch ($capability) {
            case self::CAPABILITY_PRO_BASE:
                return true;
            case self::CAPABILITY_MULTISITE:
                return $basicMultisite;
            case self::CAPABILITY_IMPORT:
            case self::CAPABILITY_SCHEDULE:
            case self::CAPABILITY_STORAGE:
            case self::CAPABILITY_TEMPLATE:
                if (static::isMultisite()) {
                    return $basicMultisite;
                } else {
                    return $license > 0;
                }
            case self::CAPABILITY_BRAND:
            case self::CAPABILITY_IMPORT_SETTINGS:
            case self::CAPABILITY_SHEDULE_HOURLY:
            case self::CAPABILITY_POWER_TOOLS:
            case self::CAPABILITY_UPDATE_AUTH:
            case self::CAPABILITY_CHANGE_TABLE_PREFIX:
                return in_array(
                    $license,
                    [
                        self::TYPE_FREELANCER,
                        self::TYPE_FREELANCER_AUTO,
                        self::TYPE_BUSINESS,
                        self::TYPE_BUSINESS_AUTO,
                        self::TYPE_GOLD,
                        self::TYPE_PLUS,
                        self::TYPE_PRO,
                        self::TYPE_ELITE,
                    ]
                );
            case self::CAPABILITY_MULTISITE_PLUS:
            case self::CAPABILITY_CAPABILITIES_MNG:
            case self::CAPABILITY_CAPABILITIES_MNG_PLUS:
            case self::CAPABILITY_PACKAGE_COMPONENTS_PLUS:
                return in_array(
                    $license,
                    [
                        self::TYPE_BUSINESS,
                        self::TYPE_BUSINESS_AUTO,
                        self::TYPE_GOLD,
                        self::TYPE_PRO,
                        self::TYPE_ELITE,
                    ]
                );
            default:
                return false;
        }
    }

    /**
     * Return true if license can be upgraded
     *
     * @param ?int $license ENUM license type, if null Get currnt licnse type
     *
     * @return bool
     */
    public static function canBeUpgraded($license = null)
    {
        if (is_null($license)) {
            $license = static::getType();
        }

        return !in_array($license, [
            self::TYPE_BUSINESS,
            self::TYPE_BUSINESS_AUTO,
            self::TYPE_GOLD,
            self::TYPE_ELITE,
        ]);
    }

    /**
     * Return license description
     *
     * @param ?int $license ENUM license type, if null Get currnt licnse type
     * @param bool $article if true add article before description
     *
     * @return string
     */
    public static function getLicenseToString($license = null, $article = false)
    {
        if (is_null($license)) {
            $license = static::getType();
        }

        switch ($license) {
            case self::TYPE_UNLICENSED:
                return ($article ? 'an ' : '') . 'Unlicensed';
            case self::TYPE_PERSONAL:
            case self::TYPE_PERSONAL_AUTO:
                return ($article ? 'a ' : '') . 'Personal';
            case self::TYPE_FREELANCER:
            case self::TYPE_FREELANCER_AUTO:
                return ($article ? 'a ' : '') . 'Freelancer';
            case self::TYPE_BUSINESS:
            case self::TYPE_BUSINESS_AUTO:
                return ($article ? 'a ' : '') . 'Business';
            case self::TYPE_GOLD:
                return ($article ? 'a ' : '') . 'Gold';
            case self::TYPE_BASIC:
                return ($article ? 'a ' : '') . 'Basic';
            case self::TYPE_PLUS:
                return ($article ? 'a ' : '') . 'Plus';
            case self::TYPE_PRO:
                return ($article ? 'a ' : '') . 'Pro';
            case self::TYPE_ELITE:
                return ($article ? 'a ' : '') . 'Elite';
            default:
                return ($article ? 'an ' : '') . 'Unknown Type';
        }
    }

    /**
     * Get best license from two given
     *
     * @param int $l1 ENUM license
     * @param int $l2 ENUM license
     *
     * @return int ENUM license
     */
    protected static function getBestLicense($l1, $l2)
    {
        $l1Weight = 0;
        $l2Weight = 0;

        $wheigts = [
            self::TYPE_UNLICENSED      => -1,
            self::TYPE_BASIC           => 0,
            self::TYPE_PERSONAL        => 1,
            self::TYPE_PERSONAL_AUTO   => 1,
            self::TYPE_PLUS            => 2,
            self::TYPE_FREELANCER      => 3,
            self::TYPE_FREELANCER_AUTO => 3,
            self::TYPE_PRO             => 4,
            self::TYPE_ELITE           => 5,
            self::TYPE_BUSINESS        => 6,
            self::TYPE_BUSINESS_AUTO   => 6,
            self::TYPE_GOLD            => 7,
        ];

        $l1Weight = ($wheigts[$l1] ?? -1);
        $l2Weight = ($wheigts[$l2] ?? -1);

        return ($l1Weight >= $l2Weight ? $l1 : $l2);
    }
}
