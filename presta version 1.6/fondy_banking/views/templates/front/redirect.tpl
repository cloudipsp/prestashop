{nocache}
    <p class="payment_module">
        {l s='Wait for redirection, or submit form' mod='fondy_banking'}
        <br/>
        <br/>
        <button style=" outline: none;
                        border-radius: 7px;
                        text-decoration: none;
                        box-sizing: content-box;
                        width: 200px;
                        text-transform: uppercase;
                        padding: 0;
                        color: #fcfcfc;
                        border-color: #62ba46;
                        background: #62ba46;
                        border-width: 10px;
                        border-style: solid;
    border-image-outset: 4px;" onclick="javascript:$('#fondy').submit();" title="{l s='Pay via Fondy' mod='fondy_banking'}">
            {l s='To checkout' mod='fondy_banking'}
        </button>
    </p>
    <form id="fondy" method="post" action="{$fondy_url|escape:'htmlall'}">
        <input type="hidden" name="order_id" value="{$order_id|escape:'htmlall'}"/>
        <input type="hidden" name="merchant_id" value="{$merchant_id|escape:'htmlall'}"/>
        <input type="hidden" name="order_desc" value="{$order_desc|escape:'htmlall'}"/>
        <input type="hidden" name="amount" value="{$amount|escape:'htmlall'}"/>
        <input type="hidden" name="currency" value="{$currency|escape:'htmlall'}"/>
        <input type="hidden" name="server_callback_url" value="{$server_callback_url|escape:'htmlall'}"/>
        <input type="hidden" name="response_url" value="{$response_url|escape:'htmlall'}"/>
        <input type="hidden" name="lang" value="{$lang|escape:'htmlall'}"/>
        <input type="hidden" name="sender_email" value="{$sender_email|escape:'htmlall'}"/>
        <input type="hidden" name="default_payment_system" value="{$default_payment_system|escape:'htmlall'}"/>
        <input type="hidden" name="signature" value="{$signature|escape:'htmlall'}"/>
        <input type="hidden" value="{l s='Pay' mod='fondy_banking'}">
    </form>
    <script type="text/javascript">
        $(document).ready(function () {
            $('#fondy').submit();
        });
    </script>
{/nocache}