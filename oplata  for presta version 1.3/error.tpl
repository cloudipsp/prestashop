{capture name=path}<a href="{$base_dir_ssl}order.php">{l s='Your shopping cart' mod='oplata'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Oplata payment' mod='Oplata'}{/capture}
{include file=$tpl_dir./breadcrumb.tpl}

<h2>Oplata payment</h2>
<div class="error">{$message}</div>
<br><br>
<a href="{$base_dir_ssl}history.php">{l s='Complete your order' mod='oplata'}</a>
