<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

require_once 'magicsig.php';

class MagicEnvelope
{
    const ENCODING = 'base64url';

    const NS = 'http://salmon-protocol.org/ns/magic-env';
    
    private function normalizeUser($user_id)
    {
        if (substr($user_id, 0, 5) == 'http:' ||
            substr($user_id, 0, 6) == 'https:' ||
            substr($user_id, 0, 5) == 'acct:') {
            return $user_id;
        }

        if (strpos($user_id, '@') !== FALSE) {
            return 'acct:' . $user_id;
        }

        return 'http://' . $user_id;
    }

    public function getKeyPair($signer_uri)
    {
        return 'RSA.79_L2gq-TD72Nsb5yGS0r9stLLpJZF5AHXyxzWmQmlqKl276LEJEs8CppcerLcR90MbYQUwt-SX9slx40Yq3vA==.AQAB.AR-jo5KMfSISmDAT2iMs2_vNFgWRjl5rbJVvA0SpGIEWyPdCGxlPtCbTexp8-0ZEIe8a4SyjatBECH5hxgMTpw==';
    }


    public function signMessage($text, $mimetype, $signer_uri)
    {
        $signer_uri = $this->normalizeUser($signer_uri);

        if (!$this->checkAuthor($text, $signer_uri)) {
            return false;
        }

        $signature_alg = new MagicsigRsaSha256($this->getKeyPair($signer_uri));
        $armored_text = base64_encode($text);

        return array(
            'data' => $armored_text,
            'encoding' => MagicEnvelope::ENCODING,
            'data_type' => $mimetype,
            'sig' => $signature_alg->sign($armored_text),
            'alg' => $signature_alg->getName()
        );
            
            
    }

    public function unfold($env)
    {
        $dom = new DOMDocument();
        $dom->loadXML(base64_decode($env['data']));

        if ($dom->documentElement->tagName != 'entry') {
            return false;
        }

        $prov = $dom->createElementNS(MagicEnvelope::NS, 'me:provenance');
        $prov->setAttribute('xmlns:me', MagicEnvelope::NS);
        $data = $dom->createElementNS(MagicEnvelope::NS, 'me:data', $env['data']);
        $data->setAttribute('type', $env['data_type']);
        $prov->appendChild($data);
        $enc = $dom->createElementNS(MagicEnvelope::NS, 'me:encoding', $env['encoding']);
        $prov->appendChild($enc);
        $alg = $dom->createElementNS(MagicEnvelope::NS, 'me:alg', $env['alg']);
        $prov->appendChild($alg);
        $sig = $dom->createElementNS(MagicEnvelope::NS, 'me:sig', $env['sig']);
        $prov->appendChild($sig);

        $dom->documentElement->appendChild($prov);

        return $dom->saveXML();
    }
    
    public function getAuthor($text) {
        $doc = new DOMDocument();
        if (!$doc->loadXML($text)) {
            return FALSE;
        }

        if ($doc->documentElement->tagName == 'entry') {
            $authors = $doc->documentElement->getElementsByTagName('author');
            foreach ($authors as $author) {
                $uris = $author->getElementsByTagName('uri');
                foreach ($uris as $uri) {
                    return $this->normalizeUser($uri->nodeValue);
                }
            }
        }
    }
    
    public function checkAuthor($text, $signer_uri)
    {
        return ($this->getAuthor($text) == $signer_uri);
    }
    
    public function verify($env)
    {
        if ($env['alg'] != 'RSA-SHA256') {
            return false;
        }

        if ($env['encoding'] != MagicEnvelope::ENCODING) {
            return false;
        }

        $text = base64_decode($env['data']);
        $signer_uri = $this->getAuthor($text);

        $verifier = new MagicsigRsaSha256($this->getKeyPair($signer_uri));

        return $verifier->verify($env['data'], $env['sig']);
    }

    public function parse($text)
    {
        $dom = DOMDocument::loadXML($text);
        return $this->fromDom($dom);
    }

    public function fromDom($dom)
    {
        if ($dom->documentElement->tagName == 'entry') {
            $env_element = $dom->getElementsByTagNameNS(MagicEnvelope::NS, 'provenance')->item(0);
        } else if ($dom->documentElement->tagName == 'me:env') {
            $env_element = $dom->documentElement;
        } else {
            return false;
        }

        $data_element = $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'data')->item(0);
        
        return array(
            'data' => trim($data_element->nodeValue),
            'data_type' => $data_element->getAttribute('type'),
            'encoding' => $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'encoding')->item(0)->nodeValue,
            'alg' => $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'alg')->item(0)->nodeValue,
            'sig' => $env_element->getElementsByTagNameNS(MagicEnvelope::NS, 'sig')->item(0)->nodeValue,
        );
    }

}
