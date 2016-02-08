{capture name=path}<a href="{$base_dir_ssl}order.php">{l s='Your shopping cart' mod='fondy'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Fondy payment' mod='Fondy'}{/capture}
{include file=$tpl_dir./breadcrumb.tpl}

<h2>Fondy payment</h2>
<div class="error">{$message}</div>
<br><br>
<a href="{$base_dir_ssl}history.php">{l s='Complete your order' mod='fondy'}</a>
