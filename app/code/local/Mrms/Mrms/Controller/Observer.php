<?php
class Mrms_Mrms_Controller_Observer{

	public function sendRequestToMrmsNonObserver($order_id){
		
		if(!Mage::getStoreConfig('mrms/basic_settings/active')){
			return true;
		}

		$order = Mage::getModel('sales/order')->load($order_id);

		if($order->getmrms_response()){
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('mrms')->__('Request already submitted to Mrms.'));
			return true;
		}

		return $this->processSendRequestToMrms($order);
	}

	public function sendRequestToMrms($observer){

		if(!Mage::getStoreConfig('mrms/basic_settings/active')){
			return true;
		}
	
		$event = $observer->getEvent();
		$order = $event->getOrder();

		if($order->getmrms_response()){
			return true;
		}

		return $this->processSendRequestToMrms($order);
	}

	public function processSendRequestToMrms($order){

		if(isset($_SERVER['DEV_MODE'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	
		$apiKey = Mage::getStoreConfig('mrms/basic_settings/api_key');
		$merchant_id = Mage::getStoreConfig('mrms/basic_settings/merchant_id');
		$site = Mage::getStoreConfig('mrms/basic_settings/site_id');
		$template_id = Mage::getStoreConfig('mrms/basic_settings/template_id');

		$billingAddress = $order->getBillingAddress();

		$shippingAddress = $order->getShippingAddress();

		$fields = array(
				'MerchantID'=>$merchant_id,
				'Key'=>$apiKey,
				'Site'=>$site,
				'TemplateID'=>$template_id,
				'GroupID'=>'',
				'SessionID'=>md5(session_id()),
				'ReferenceNo'=>	'MAG-'.$merchant_id.'-'.$order->getIncrementId(),    //ref no. combination of MAG- <merchant_id> - <order_id>
				'Amount'=> $order->getBaseGrandTotal(),
				'DateTime'=>date('Y-m-d H:i:s'),
				'CardNumberHash'=>'',
				'CardNumber'=> '',
				'CardType'=>'',
				'NameOnCard'=>'',
				'CustomerID'=>$order->getCustomerId(),
				'CustomerIsReliable'=> 'N',
				'CustEmail'=>$order->getCustomerEmail(),
				'CustPhone'=>($billingAddress->getTelephone() == '') ? '0000000000000000' : $billingAddress->getTelephone(),
				'UserMD5'=>strtoupper(md5($order->getCustomerEmail())),
				'PassMD5'=>'',
				'Name'=>$order->getCustomerName(),
				'Address'=>$billingAddress->getStreet(1) .' '. $billingAddress->getStreet(2),
				'City'=>$billingAddress->getCity(),
				'Region'=>$billingAddress->getRegion(),
				'Postal'=>$billingAddress->getPostcode(),
				'Country'=>$billingAddress->getCountryId(),
				'ShipName'=>'',
				'ShipAddress'=>trim($shippingAddress->getStreet(1) . ' ' . $shippingAddress->getStreet(2)),
				'ShipCity'=>$shippingAddress->getCity(),
				'ShipState'=>$shippingAddress->getRegion(),
				'ShipPostal'=>$shippingAddress->getPostcode(),
				'ShipCountry'=>$shippingAddress->getCountryId(),
				'ShipEmail'=>$shippingAddress->getEmail(),
				'ShipPhone'=>'',
				'ShipPeriod'=>0,
				'ShipMethod'=>'',
				'Products'=>'',
				'Ip'=>'',
				'Submit'=>'Submit'
				);
		
		$fields_string = http_build_query($fields);
 	
		$url = "https://s1.rmsid.com/fde/api/txn/Post.xml";
		if(isset($_SESSION['mrms_txn']) && $_SESSION['mrms_txn'] == $order->getIncrementId()){
			unset($_SESSION['mrms_txn']);
			return false;	
		}
		$result = $this->_get($url,count($fields),$fields_string);
		$_SESSION['mrms_txn'] = $order->getIncrementId();
		$json = json_encode(simplexml_load_string($result));
		$response = json_decode($json,true);

		if(!$response) return false;
		
		if(isset($response['Error'])){
			if($response['Error'] == '256-ReferenceNo is already exists'){
				return false;
			}else{
				$order->setmrms_response(serialize($response))->save();
				return false;
			}
		}

		$response['api_key'] = $apiKey;

		$order->setmrms_response(serialize($response))->save();
		
		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('mrms')->__('Mrms Request sent.'));
		
		return true;
	}

	private function _get($url,$paramscount,$params){
		
		$ch = curl_init();

		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, $paramscount);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
		
		//execute post
		$result = curl_exec($ch);

		if(!curl_errno($ch)) return $result;
		else{
			$errXML = "<?xml version='1.0'?><RMSID><Error>Curl error no.".curl_errno($ch)."</Error></RMSID>";
			return $errXML;
		}

		curl_close($ch);

		return false;
	}

	private function _hash($s, $prefix='mrms_'){
		$hash = $prefix . $s;
		for($i=0; $i<65536; $i++) $hash = sha1($prefix . $hash);

		return $hash;
	}
}