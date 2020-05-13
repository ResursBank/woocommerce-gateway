<?php

namespace TorneLIB\Module\Network;

use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Address IP- and arpas.
 *
 * @package TorneLIB\Module\Network
 * @version 6.1.0
 */
class Address
{

    /**
     * Get IP range from netmask.
     *
     * @param null $mask
     * @return array
     * @since 5.0.0
     */
    public function getRangeFromMask($mask = null)
    {
        $addresses = [];
        @list($ip, $len) = explode('/', $mask);
        if (($min = ip2long($ip)) !== false) {
            $max = ($min | (1 << (32 - $len)) - 1);
            for ($i = $min; $i < $max; $i++) {
                $addresses[] = long2ip($i);
            }
        }

        return $addresses;
    }

    /**
     * Test if the given ip address is in the netmask range (not ipv6 compatible yet)
     *
     * @param $IP
     * @param $CIDR
     * @return bool
     * @since 5.0.0
     */
    public function isIpInRange($IP, $CIDR)
    {
        list($net, $mask) = explode("/", $CIDR);
        $ip_net = ip2long($net);
        $ip_mask = ~((1 << (32 - $mask)) - 1);
        $ip_ip = ip2long($IP);
        $ip_ip_net = $ip_ip & $ip_mask;

        return ($ip_ip_net == $ip_net);
    }

    /**
     * Translate ipv6 address to reverse octets
     *
     * @param string $ipAddr
     * @return string
     * @since 5.0.0
     */
    public function getArpaFromIpv6($ipAddr = '::')
    {
        if (filter_var($ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }
        $unpackedAddr = @unpack('H*hex', inet_pton($ipAddr));
        $hex = $unpackedAddr['hex'];

        return implode('.', array_reverse(str_split($hex)));
    }

    /**
     * Translate ipv4 address to reverse octets
     *
     * @param string $ipAddr
     * @return string
     * @since 5.0.0
     */
    public function getArpaFromIpv4($ipAddr = '127.0.0.1')
    {
        if (filter_var($ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return implode(".", array_reverse(explode(".", $ipAddr)));
        }

        return null;
    }

    /**
     * Translate ipv6 reverse octets to ipv6 address
     *
     * @param string $arpaOctets
     * @return string
     * @since 5.0.0
     */
    public function getIpv6FromOctets(
        $arpaOctets = '0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0'
    ) {
        return @inet_ntop(
            pack(
                'H*',
                implode(
                    "",
                    array_reverse(
                        explode(
                            ".",
                            preg_replace(
                                "/\.ip6\.arpa$|\.ip\.int$/",
                                '',
                                $arpaOctets
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param $ipAddress
     * @return bool
     */
    private function isIpv6($ipAddress)
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    private function isIpv4($ipAddress)
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * @param $ipAddress
     * @param bool $returnIpType
     * @return int|string
     * @throws ExceptionHandler
     * @since 5.0.0
     */
    public function getArpaFromAddr($ipAddress, $returnIpType = false)
    {
        $return = '0';
        if ($this->isIpv6($ipAddress)) {
            if ($returnIpType) {
                $return = 6;
            } else {
                $return = $this->getArpaFromIpv6($ipAddress);
            }
        } elseif ($this->isIpv4($ipAddress)) {
            if ($returnIpType) {
                $return = 4;
            } else {
                $return = $this->getArpaFromIpv4($ipAddress);
            }
        } else {
            throw new ExceptionHandler(
                sprintf(
                    'Invalid ip address "%s" in request %s.',
                    $ipAddress,
                    __FUNCTION__
                ),
                Constants::LIB_NETCURL_INVALID_IP_ADDRESS
            );
        }

        return $return;
    }

    /**
     * @param $ipAddress
     * @return int
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getArpa($ipAddress)
    {
        return $this->getArpaFromAddr($ipAddress);
    }

    /**
     * Get type of ip address. Returns 0 if no type. IP Protocols from netcurl is deprecated.
     *
     * @param $ipAddress
     * @return int
     * @throws ExceptionHandler
     * @since 6.1.0
     */
    public function getIpType($ipAddress)
    {
        return $this->getArpaFromAddr($ipAddress, true);
    }
}
