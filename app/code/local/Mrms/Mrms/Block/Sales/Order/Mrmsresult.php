<?php
class Mrms_Mrms_Block_Sales_Order_Mrmsresult extends Mage_Adminhtml_Block_Template{
    protected function _toHtml(){
		if(!Mage::getStoreConfig('mrms/basic_settings/active')){
			return false;
		}

		$order = Mage::registry('current_order');

		$data = unserialize($order->getmrms_response());

		if(isset($_GET['mrms_refresh'])){
		    $params = array(
				   'MerchantID'=>Mage::getStoreConfig('mrms/basic_settings/merchant_id'),
				   'Key'=>Mage::getStoreConfig('mrms/basic_settings/api_key'),
				   'TxnLogID'=>$data['TxnLogID']
				   );
		    $response = $this->_get('https://s1.rmsid.com/fde/api/txn/GetByID.xml',count($params),$params);
		    if($response){
			$json = json_encode(simplexml_load_string($response));
			$result = json_decode($json,true);
			$data['PaymentStatus'] = $result['PaymentStatus'];
			$order->setmrms_response(serialize($data))->save();
		    }
		    die(header('Location: ' . substr(Mage::helper('core/url')->getCurrentUrl(), 0, strpos(Mage::helper('core/url')->getCurrentUrl(), '?'))));
		    
		}
		if(isset($data['Error'])){
		    $data = 0;
		}
		
		if(!$data) return '
		    <div class="entry-edit">
			<div class="entry-edit-head" style="background:#cc0000;">
				<h4 class="icon-head head-shipping-method">MerchantRMS</h4>
			</div>
			<fieldset>
				This order is not procssed by MerchantRMS.
			</fieldset>
		</div>';
		
		if($data['RiskLevel'] == 'Red'){
		    $score = '<div style="color:#FF0000;font-size:4em;margin-top:20px;"><strong>'.$data['RiskPercentage'].'</strong></div>';
		}else if($data['RiskLevel'] == 'Yellow'){
		    $score = '<div style="color:#ffff00;font-size:4em;margin-top:20px;"><strong>'.$data['RiskPercentage'].'</strong></div>';
		}else{
		    $score = '<div style="color:#33CC00;font-size:3em;margin-top:20px;"><strong>'.$data['RiskPercentage'].'</strong></div>';
		}

		switch($data['PaymentStatus']){
			case 'Paid':
				$status = '<div style="color:#FFCC00;font-size:2em;margin-top:10px;"><strong>Review</strong></div>';
			break;

			case 'Rejected':
				$status = '<div style="color:#cc0000;font-size:2em;margin-top:10px;"><strong>'.$data['PaymentStatus'].'</strong></div>';
			break;

			case 'Approved':
				$status = '<div style="color:#336600;font-size:2em;margin-top:10px;"><strong>'.$data['PaymentStatus'].'</strong></div>';
			break;

			default:
				$status = '-';
		}
		$txnid = explode('-',$data['TxnLogID']);
		//$reviewLink = "<a href=http://localhost/mrms-v3/trunk/app/rms/txnlog/action/view/txnlogid/".$txnid[1].">Review</a>";
		$txnviewLink = "http://www.rmsid.com/fde/app/rms/txnlog/action/view/pageID/1/txnlogid/".$txnid[1];
		
		$out = '
		<div class="entry-edit">
			<div class="entry-edit-head" style="background:#1DA1E0; padding:5px;">
				<h4 class="icon-head head-shipping-method">MerchantRMS</h4>
			</div>
			<fieldset>
			<table width="100%" border="1" bordercolor="#c0c0c0" style="border-collapse:collapse;">
			<tr>
			    <td rowspan="3" style="width:90px; text-align:center; vertical-align:top; padding:5px;">
				<strong>Score</strong>
				<a href="javascript:;" title="Overall score between 0 and 100. 100 is the highest risk. 0 is the lowest risk.">[?]</a><br/>'. $score .'
			    </td>
			    <td style="width:120px; padding:5px;"><span><strong>MerchantRMS Id </strong></span></td>
			    <td style="width:150px; padding:5px;"><span><a href="'.$txnviewLink.'" target="_blank">'.$data['TxnLogID'].'</a></span></td>
			    <td style="width:140px; padding:5px;"><span><strong>Template Id</strong></span></td>
			    <td style="width:140px; padding:5px;"><span>' . $data['TemplateID'] . '</span></td>
			    <td style="width:120px; padding:5px;"><span><strong>Merchant Id</strong></span></td>
			    <td style="padding:5px;"><span>'.Mage::getStoreConfig('mrms/basic_settings/merchant_id') .'</span></td>
			</tr>
			<tr>
			    <td style="padding:5px;"><span><strong>Reference No</strong></span></td>
			    <td style="padding:5px;"><span>' . $data['ReferenceNo'] . '</span></td>
			    <td style="padding:5px;"><span><strong>Device Id</strong></span></td>
			    <td style="padding:5px;"><span>' . $data['DeviceID'] . '</span></td>
			    <td style="padding:5px;"><span><strong>Device Profile Status</strong></span></td>
			    <td style="padding:5px;"><span>' . $data['DeviceProfileStatus'] . '</span></td>
			</tr>
			<tr>
			    <td style="padding:5px;"><span><strong>IP City</strong></span></td>
			    <td colspan="3" style="padding:5px;">
				<span>' . (empty($data['Output']['Ipcity']) ? "-" : $data['Output']['Ipcity']). '</span>
			    </td>
			    <td style="padding:5px;"><span><strong>IP Address</strong></span></td>
			    <td style="padding:5px;">
				<span>'.$data['ip_address'].'
				    <a href="http://www.geolocation.com/' . $data['ip_address'] . '" target="_blank">[Map]</a>
				</span>
			    </td>
			</tr>
			<tr>
			    <td rowspan="4"  style="padding:5px; vertical-align:top; text-align:center;">
				<span><strong>Mrms Status</strong>
				    <a href="javascript:;" title="Mrms status.">[?]</a><br>' . $status . '</span>'. (($data['PaymentStatus'] == "Paid") ? "<br><form><input type='submit' name='mrms_refresh' value='Refresh' /></form>" : " ").'
			    </td>
			    <td style="padding:5px;"><span><strong>IP Country</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Ipcountry']) ? "-" : $data['Output']['Ipcountry']) . '</span></td>
			    <td style="padding:5px;"><span><strong>IP ISP</strong></span></td>
			    <td colspan="3" style="padding:5px;"><span>' . $data['Output']['Ipisp'] . '</span></td>
			</tr>
			<tr>
			    <td style="padding:5px;"><span><strong>IP Origin</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Iporg']) ? "-" : $data['Output']['Iporg']) . '</span></td>
			    <td style="padding:5px;"><span><strong>IP Latitude</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Iplatitude']) ? "-" : $data['Output']['Iplatitude']) . '</span></td>
			    <td style="padding:5px;"><span><strong>IP Longitude</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Iplongitude']) ? "-" : $data['Output']['Iplongitude']) . '</span></td>
			</tr>
			<tr>
			    <td style="padding:5px;"><span><strong>BIN Name</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Binname']) ? "-" : $data['Output']['Binname']) . '</span></td>
			    <td style="padding:5px;"><span><strong>BIN Country</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Bincountry']) ? "-" : $data['Output']['Bincountry']) . '</span></td>
			    <td colspan="3" style="padding:5px;"></td>
			</tr>
			<tr>
			    <td style="padding:5px;"><span><strong>Free Email</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Freeemail']) ? "-" : $data['Output']['Freeemail']) . '</span></td>
			    <td style="padding:5px;"><span><strong>Email Domain</strong></span></td>
			    <td style="padding:5px;"><span>' .(empty($data['Output']['Emaildomain']) ? "-" : $data['Output']['Emaildomain']) . '</span></td>
			    <td colspan="3" style="padding:5px;"><span>&nbsp;</span></td>
			</tr>
			<tr>
				<td style="padding:5px;"><span><strong>Message</strong></span></td>
				<td colspan="6" style="padding:5px;"><span>-</span></td>
			</tr>';

		$out .= '</table></fieldset></div>';

		return $out;
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

		curl_close($ch);

		return false;
	}

	private function _case($s){
		$s = ucwords(strtolower($s));
		$s = preg_replace_callback("/( [ a-zA-Z]{1}')([a-zA-Z0-9]{1})/s",create_function('$matches','return $matches[1].strtoupper($matches[2]);'),$s);
		return $s;
	}
}