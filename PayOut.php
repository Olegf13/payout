<?php
namespace olegf13;

use \SimpleXMLElement;

class PayOut
{
    const CURRENCY_RUR = 643;
    const CURRENCY_EUR = 978;
    const CURRENCY_USD = 840;
    const CURRENCY_ALL = 'ALL';

    private $alg = 'md5';
    private $point = '';

    public $requestUrl = 'https://payoutmoney.com/api/v1/';

    public function __construct($point = null)
    {
        if (is_numeric($point)) {
            $this->setPoint($point);
        }
    }

    /**
     * @param $url
     */
    public function setRequestUrl($url)
    {
        $this->requestUrl = $url;
    }

    /**
     * @return string
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    /**
     * @param $alg
     */
    public function setAlg($alg)
    {
        $this->alg = $alg;
    }

    /**
     * @return string
     */
    public function getAlg()
    {
        return $this->alg;
    }

    /**
     * @param $point
     */
    public function setPoint($point)
    {
        $this->point = $point;
    }

    /**
     * @return string
     */
    public function getPoint()
    {
        return $this->point;
    }

    /**
     * @throws \RuntimeException
     * @return string
     */
    public function getKeyPath()
    {
        $keyPath = __DIR__ . '/../../../keys/secret.key';
        if (file_exists($keyPath)) {
            return $keyPath;
        }

        throw new \RuntimeException("Secret key not found in `{$keyPath}`");
    }

    /**
     * @param int $currency
     * @return SimpleXMLElement
     */
    public function getBalance($currency = self::CURRENCY_RUR)
    {
        $currencySection = '';
        switch ($currency) {
            case PayOut::CURRENCY_RUR:
            case PayOut::CURRENCY_USD:
            case PayOut::CURRENCY_EUR:
                $currencySection = '<currency>' . $currency . '</currency>';
                break;
            case PayOut::CURRENCY_ALL:
                $currencySection = '<currency>ALL</currency>';
                break;
            default:
                break;
        }
        $requestMessage = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
    <action id="Agents.getBalance">
        {$currencySection}
    </action>
</request>
XML;
        return $this->buildRequest($requestMessage);
    }

    /**
     * @return mixed
     */
    public function getProviders()
    {
        $requestMessage = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
    <action id="Payments.getProviders" />
</request>
XML;
        return $this->buildRequest($requestMessage);
    }

    /**
     * @param $uid
     * @return mixed
     */
    public function getPaymentStatus($uid)
    {
        $requestMessage = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
    <action id="Payments.getPaymentStatus" >
		<payment uid="$uid" />
	</action>
</request>;
XML;
        return $this->buildRequest($requestMessage);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getPreCheckStatus($params)
    {
        $requestMessage = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
	<action id="Payments.getPrecheckStatus">
		<tocheck phone="{$params['phone']}" service="{$params['service_id']}"/>
	</action>
</request>
XML;
        return $this->buildRequest($requestMessage);
    }

    /**
     * $payment['payment_id', 'service_id', 'account']
     * @param array $payment
     * @return mixed
     */
    public function verifyPayment($payment)
    {
        $fields = '<fields>';
        foreach ($payment['fields'] as $key => $val) {
            $fields .= '<' . $key . '>' . $val . '</' . $key . '>';
        }

        $currency = '';
        if (isset($payment['currency'])) {
            $currency = '<currency>' . $payment['currency'] . '</currency>';
        }

        $fields .= '</fields>';

        $requestMessage = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
    <action id="Payments.verifyPayment" >
		<payment id="{$payment['payment_id']}">
			<serviceId>{$payment['service_id']}</serviceId>
            {$fields}
			<amount>{$payment['amount']}</amount>
            {$currency}
		</payment>
	</action>
</request>
XML;
        return $this->buildRequest($requestMessage);
    }

    /**
     * $payment['payment_id', 'service_id', 'account', 'amount', 'data', 'comment']
     * @param array $payment
     * @return mixed
     */
    public function createPayment($payment)
    {
        $fields = '<fields>';
        foreach ($payment['fields'] as $key => $val) {
            $fields .= '<' . $key . '>' . $val . '</' . $key . '>';
        }
        $fields .= '</fields>';

        $currency = '';
        if (isset($payment['currency'])) {
            $currency = '<currency>' . $payment['currency'] . '</currency>';
        }

        $requestMessage = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<request>
	<action id="Payments.createPayment">
		<payment id="{$payment['payment_id']}">
		    <serviceId>{$payment['service_id']}</serviceId>
            {$fields}
		    <amount>{$payment['amount']}</amount>
		    <dateTime>{$payment['data']}</dateTime>
		    <comment>{$payment['comment']}</comment>
            {$currency}
	    </payment>
    </action>
</request>
XML;
        return $this->buildRequest($requestMessage);
    }

    /**
     * @param $xml
     * @return mixed
     */
    private function buildRequest($xml)
    {
        $xml = str_replace(["\n", "\t"], '', $xml);

        $headers = [
            'Content-Type: text/xml',
            'Amega-Sign: ' . $this->getSign($xml),
            'Amega-Hash-Alg: ' . $this->alg,
            'Amega-UserId: ' . $this->point,
            'Amega-ProtocolVersion: 1',
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->getRequestUrl());
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
//		curl_setopt($curl, CURLOPT_HEADER, true);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    /**
     * @param $requestMessage
     * @throws \RuntimeException
     * @return string
     */
    private function getSign($requestMessage)
    {
        if (file_put_contents('tmp.xml', $requestMessage)) {
            $cmd = stristr(php_uname('s'), 'windows') ? 'type' : 'cat';
            $handle = popen("$cmd tmp.xml | openssl dgst -{$this->alg} -binary -keyform DER -sign {$this->getKeyPath()} | openssl base64 -A", 'r');
            $read = fread($handle, 2096);
            pclose($handle);
            unlink('tmp.xml');
            return $read;
        } else {
            throw new \RuntimeException('No rights for temporary file creation.');
        }
    }
}