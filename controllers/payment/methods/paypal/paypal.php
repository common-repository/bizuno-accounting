<?php
/*
 * Payment Method - PayPal thought hosted payment terminal @paypal.com
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2024, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    6.x Last Update: 2024-03-01
 * @filesource /lib/controllers/payment/methods/paypal.php
 */

namespace bizuno;

class paypal
{
    public  $moduleID = 'payment';
    public  $methodDir= 'methods';
    public  $code     = 'paypal';

    public function __construct()
    {
        $this->lang    = getMethLang   ($this->moduleID, $this->methodDir, $this->code);
        $pmtDef        = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $this->settings= ['cash_gl_acct'=>$pmtDef['gl_payment_c'],'disc_gl_acct'=>$pmtDef['gl_discount_c'],'prefix'=>'PP','order'=>30];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'cash_gl_acct'=> ['label'=>$this->lang['gl_payment_c_lbl'], 'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_cash_gl_acct",'value'=>$this->settings['cash_gl_acct']]],
            'disc_gl_acct'=> ['label'=>$this->lang['gl_discount_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_disc_gl_acct",'value'=>$this->settings['disc_gl_acct']]],
            'prefix'      => ['label'=>$this->lang['prefix_lbl'], 'position'=>'after', 'attr'=>['size'=>'5', 'value'=>$this->settings['prefix']]],
            'order'       => ['label'=>lang('order'), 'position'=>'after', 'attr'=>['type'=>'integer', 'size'=>'3', 'value'=>$this->settings['order']]]];
    }

    public function render($data, $values=[], $dispFirst=false)
    {
        $this->viewData = ['ref_1'=> ['options'=>['width'=>150],'label'=>lang('journal_main_invoice_num_2'),'break'=>true, 'attr'=>['size'=>'19']]];
        if (is_array($values) && isset($values[1]) && $values[1] == $this->code) {
            $this->viewData['ref_1']['attr']['value'] = isset($values[2]) ? $values[2] : '';
            $invoice_num = $data['fields']['invoice_num']['attr']['value'];
            $gl_account  = $data['fields']['gl_acct_id']['attr']['value'];
            $discount_gl = $this->getDiscGL($data['fields']['id']['attr']['value']);
        } else {
            $invoice_num = $this->settings['prefix'].biz_date('Ymd');
            $gl_account  = $this->settings['cash_gl_acct'];
            $discount_gl = $this->settings['disc_gl_acct'];
        }
        htmlQueue("
arrPmtMethod['$this->code'] = {cashGL:'$gl_account',discGL:'$discount_gl',ref:'$invoice_num'};
function payment_{$this->code}() {
    if (!jqBiz('#id').val()) {
        bizTextSet('invoice_num', arrPmtMethod['$this->code'].ref);
        bizSelSet('gl_acct_id', arrPmtMethod['$this->code'].cashGL);
        bizSelSet('totals_discount_gl', arrPmtMethod['$this->code'].discGL);
    }
}", 'jsHead');
        if ($this->code == $dispFirst) { htmlQueue("bizTextSet('invoice_num', '$invoice_num');", 'jsReady'); }
        $html  = html5($this->code.'_action', ['label'=>$this->lang['at_paypal'],'attr'=>['type'=>'radio','value'=>'w','checked'=>true]]).'<br />';
        return $html;
    }

    public function sale($fields)
    {
        return ['txID'=>$fields['ref_1'], 'txTime'=>biz_date('c')];
    }

    /**
     * @method paymentDelete - This method will delete/void a payment made BEFORE the processor commits the payment, typically must be run the same day as the sale
     * @param string $request - data from which to pull transaction and perform delete
     * @return boolean - true on success, false (with messageStack message) on unsuccessful deletion
     */
    public function paymentDelete()
    {
        msgAdd('Deletions through the PayPal API must be handled directly through the PayPal website.', 'caution');
        return true;
    }

    /**
     * @param string $request - data from which to pull transaction and perform refund/credit
     * @return boolean - true on success, false (with messageStack message) on unsuccessful deletion
     */
    public function refund()
    {
        msgAdd('Refunds through the PayPal API must be handled directly through the PayPal website.', 'caution');
        return true;
    }

    private function getDiscGL($data)
    {
        if (isset($data['journal_item'])) {
            foreach ($data['journal_item'] as $row) {
                if ($row['gl_type'] == 'dsc') { return $row['gl_account']; }
            }
        }
        return $this->settings['disc_gl_acct']; // not found, return default
    }
}