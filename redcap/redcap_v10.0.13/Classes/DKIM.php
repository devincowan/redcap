<?php

class DKIM
{
	public $DKIM_selector = 'redcap';
	public $DKIM_passphrase = '';
	public $DKIM_dns_check_var = '__RedcapDkimDnsTxtRecordStatus';
	public $DKIM_domain;
	public $privateKey;

	public function __construct()
	{
		// Get the private key from the redcap_config table
		$this->privateKey = $GLOBALS['dkim_private_key'];
		$this->DKIM_domain = SERVER_NAME;
	}

	// Return boolean if we have all we need to perform DKIM in outgoing emails
	public function isEnabled()
	{
		return ($this->hasPrivateKey() && $this->hasDkimDnsTxtRecord());
	}

	public function hasDkimDnsTxtRecord()
	{
		// Always return true here since it doesn't hurt if DKIM is attempted and fails
		return true;

		// If this value is already stored in the session, then use the session value
		if (isset($GLOBALS[$this->DKIM_dns_check_var])) {
			return $GLOBALS[$this->DKIM_dns_check_var];
		}
		// Default session var
		$GLOBALS[$this->DKIM_dns_check_var] = false;
		// Check this server's DNS TXT record for "v=DKIM1;" in the value
		$dnsRecord = dns_get_record($this->getDnsKeyName(), DNS_TXT);
		if (is_array($dnsRecord)) {
			foreach ($dnsRecord as $item) {
				if (isset($item['txt']) && strpos($item['txt'], 'v=DKIM1;') !== false) {
					// Set a session variable for this so that we don't have to call dns_get_record() multiple times in the same request
					$GLOBALS[$this->DKIM_dns_check_var] = true;
					break;
				}
			}
		}

		return $GLOBALS[$this->DKIM_dns_check_var];
	}

	public function hasPrivateKey()
	{
		// Check the private key
		return ($this->privateKey != '');
	}

	public function createPrivateKey()
	{
		// Check the private key
		if ($this->hasPrivateKey()) return false;
		if (!function_exists('openssl_pkey_new')) return false;
		// Create a 2048-bit RSA key with an SHA256 digest
		$pk = openssl_pkey_new([
			'digest_alg' => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA
		]);
		// Generate private key
		openssl_pkey_export($pk, $privateKey, $dkim->DKIM_passphrase);
		if ($privateKey != '') {
			$this->privateKey = $privateKey;
			// Save private key to redcap_config
			$sql = "replace into redcap_config (field_name, value) values ('dkim_private_key', '".db_escape($privateKey)."')";
			if (db_query($sql)) return true;
		}
		return false;
	}

	public function generatePublicKey()
	{
		// Check the private key
		if (!$this->hasPrivateKey()) return false;
		if (!function_exists('openssl_pkey_get_private')) return false;
		// Obtain private key
		$pk = openssl_pkey_get_private($this->privateKey);
		// Public key as PEM string
		$publicKey = openssl_pkey_get_details($pk);
		$publicKey = $publicKey['key'];
		return ($publicKey == '' ? false : $publicKey);
	}

	private function getDnsKeyName()
	{
		return $this->DKIM_selector."._domainkey.".$this->DKIM_domain;
	}

	public function getDnsTxtRecordSuggestion()
	{
		$publicKey = $this->generatePublicKey();
		if ($publicKey === false) return '';
		// Prep public key for DNS, e.g.
		$dnskey = $this->getDnsKeyName();
		$dnsvalue = '"v=DKIM1; h=sha256; t=s; p=" ';
		// Some DNS server don't like ; chars unless backslash-escaped
		$dnsvalue2 = '"v=DKIM1\; h=sha256\; t=s\; p=" ';
		// Strip and split the key into smaller parts and format for DNS
		// Many DNS systems don't like long TXT entries
		// but are OK if it's split into 255-char chunks
		// Remove PEM wrapper
		$publicKey = preg_replace('/^-+.*?-+$/m', '', $publicKey);
		// Strip line breaks
		$publicKey = str_replace(["\r", "\n"], '', $publicKey);
		// Split into chunks
		$keyparts = str_split($publicKey, 253); //Becomes 255 when quotes are included
		// Quote each chunk
		foreach ($keyparts as $keypart) {
			$dnsvalue .= '"' . trim($keypart) . '" ';
			$dnsvalue2 .= '"' . trim($keypart) . '" ';
		}
		// Build output
		$str  = "<h4>Create a new TXT record in DNS for \"{$this->DKIM_domain}\"</h4>";
		$str .= "<div class='my-3'>Copy and paste the text in the boxes below, and then add both the key and value to a new TXT record in the DNS listing for this REDCap server.
				 This will enable DKIM for all outgoing emails sent from this REDCap server.</div>";
		$str .= "<div class='boldish'>DNS key:</div><pre onclick='this.select();'>" . trim($dnskey) . "</pre>";
		$str .= "<div class='boldish'>DNS value:</div><pre>" . trim($dnsvalue) . "</pre>";
		$str .= "<div class='boldish'>DNS value (with escaping):</div><pre>" . trim($dnsvalue2) . "</pre>";
		// Return output
		return $str;
	}
}