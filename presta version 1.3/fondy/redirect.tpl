{l s='Ожидание перенаправления' mod='fondy'}

<form id="fondy" method="post" action="{$fondy_url}">
	<input type="hidden" name="order_id" value="{$order_id}" />
	<input type="hidden" name="merchant_id" value="{$merchant_id}" />
	<input type="hidden" name="order_desc" value="{$order_desc}" />
	<input type="hidden" name="amount" value="{$amount}" />
	<input type="hidden" name="currency" value="{$currency}" />
	<input type="hidden" name="server_callback_url" value="{$server_callback_url}" />
	<input type="hidden" name="response_url" value="{$response_url}" />
    <input type="hidden" name="lang" value="{$lang}" />
    <input type="hidden" name="sender_email" value="{$sender_email}" />
    <input type="hidden" name="signature" value="{$signature}" />
    <input type="hidden" name="delayed" value="N" />
    <input type="submit" value="{l s='Оплатить' mod='fondy'}">
</form>

<script type="text/javascript">
	$('#fondy').submit();
</script>