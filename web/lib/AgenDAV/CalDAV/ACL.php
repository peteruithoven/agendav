<?php

namespace AgenDAV\CalDAV;

/*
 * Copyright 2012 Jorge López Pérez <jorge@adobo.org>
 *
 *  This file is part of AgenDAV.
 *
 *  AgenDAV is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  any later version.
 *
 *  AgenDAV is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with AgenDAV.  If not, see <http://www.gnu.org/licenses/>.
 */

class ACL implements IACL
{
    /**
     * Principals which have been given additional permissions
     *
     * @var Array Associative array (principal href => array of permissions)
     * @access private
     */
    private $additional_principals;

    /**
     * Default options and permissions
     *
     * @var Array
     * @access private
     */
    private $options;

    /**
     * ACL namespaces
     *
     * @var Array (name => namespace)
     * @access private
     */
    private $namespaces;

    /**
     * Special user profile names
     */
    public static $profiles = array('owner', 'authenticated', 'unauthenticated');

    public function __construct($options = array())
    {
        $this->additional_principals = array();
        if (!is_array($options) || count($options) > 0) {
            $this->setOptions($options);
        }
        $this->namespaces = array(
            '' => 'DAV:',
            'C' => 'urn:ietf:params:xml:ns:caldav',
        );
    }

    /**
     * Gets default options for this ACL
     * 
     * @access public
     * @return Array With the following keys: owner, authenticated, unauthenticated,
     *               share_read, share_rw
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Change permissions configuration 
     * 
     * @param Array $permissions With the following keys: owner, authenticated, unauthenticated,
     *               share_read, share_rw
     * @access public
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setOptions($options)
    {
        if (is_array($options)) {
            foreach (self::$profiles as $k) {
                if (!isset($options[$k])) {
                    throw new \InvalidArgumentException();
                }
            }

            $this->options = $options;
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * Adds an ACE for a principal 
     * 
     * @param string $href Principal href (relative URL)
     * @param Array $perms Array of permissions
     * @access public
     * @return void
     * @throws \InvalidArgumentException
     */
    public function addPrincipal($href, $perms)
    {
        if (!is_array($perms)) {
            throw new \InvalidArgumentException();
        }
        $this->additional_principals[$href] = $perms;
    }

    /**
     * Removes an ACE for a principal
     * 
     * @param string $href 
     * @access public
     * @return boolean true on success, false if principal wasn't included
     */
    public function removePrincipal($href)
    {
        if (isset($this->additional_principals[$href])) {
            unset($this->additional_principals[$href]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Parses an XML document containing an ACL
     * 
     * @param string $xmldoc 
     * @access public
     * @return void
     * @throws \InvalidArgumentException
     */
    public function parse($xmldoc)
    {
        // TODO
    }

    /**
     * Generates ACL XML for this entry 
     * 
     * @access public
     * @return string XML document
     */
    public function getXML()
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $acl = $dom->createElement('acl');
        foreach ($this->namespaces as $prefix => $ns) {
            $prefix = (empty($prefix) ? $prefix : ':' . $prefix);
            $acl->setAttribute('xmlns' . $prefix, $ns);
        }

        // Add all ACEs
        foreach (array('owner', 'authenticated', 'unauthenticated') as $special_profile) {
            $acl->appendChild($this->generateACE($dom, $special_profile));
        }
        foreach ($this->additional_principals as $href => $perms) {
            $acl->appendChild($this->generateACE($dom, 'principal', $href, $perms));
        }

        $dom->appendChild($acl);
        return $dom->saveXML();
    }


    /**
     * Generates an ACE element to be used inside an ACL element
     * 
     * @param \DOMDocument $d DOM Document to append the new <ace> element
     * @param string $type Profile name (one of owner, authenticated, unauthenticated or principal)
     * @param string $principal_href Principal to add
     * @param Array $perms Permissions to add for given principal
     * @access public
     * @return void
     */
    public function generateACE(\DOMDocument $d, $type, $principal_href = '', $principal_perms = array())
    {
        $ace = $d->createElement('ace');

        // Affected principal
        $principal = $d->createElement('principal');
        $affected_principal = null;

        if ($type == 'owner') {
            $property = $d->createElement('property');
            $owner = $d->createElement('owner');
            $property->appendChild($owner);
            $affected_principal = $property;
        } elseif ($type == 'principal') {
            $affected_principal = $d->createElement('href');
            $affected_principal->appendChild($d->createTextNode($principal_href));
        } else {
            $affected_principal = $d->createElement($type);
        }

        $principal->appendChild($affected_principal);

        $ace->appendChild($principal);

        // Permissions
        $grant = $d->createElement('grant');
        if ($type != 'principal') {
            $perms = $this->options[$type];
        } else {
            $perms = $principal_perms;
        }

        foreach ($perms as $permission) {
            $privilege = $d->createElement('privilege');
            $privilege->appendChild($d->createElement($permission));
            $grant->appendChild($privilege);
        }
        $ace->appendChild($grant);

        return $ace;
    }
}