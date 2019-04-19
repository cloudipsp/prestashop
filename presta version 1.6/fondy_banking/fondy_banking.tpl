{if $eu_banks_enabled eq true}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <a style="padding:10px 20px"
                   href="{$link->getModuleLink('fondy_banking', 'redirect', ['id_cart' => {$id}])}"
                   title="{l s='Pay fondy' mod='fondy_banking'}">
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/csob.svg" alt="1"/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/mbank.svg" alt="1"/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/otp.svg" alt="1"/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/pabk.svg"
                         alt="1"/><br/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/prima.svg" alt="1"/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/sberbank.svg"
                         alt="1"/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/slsp.svg" alt="1"/>
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/banks/vub.svg" alt="1"/>
                    <fondy style="position: relative;bottom: 25px;">{$this_description}</fondy>
                </a>
            </p>
        </div>
    </div>
{/if}
{if $pl_banks_enabled eq true}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <a style="padding:10px 20px"
                   href="{$link->getModuleLink('fondy_banking', 'redirect', ['id_cart' => {$id}, 'pl_banks_enabled' => {$pl_banks_enabled}])}"
                   title="{l s='Pay fondy' mod='fondy_banking'}">
                    <img style="margin-right: 10px" width="50" src="{$this_path nofilter}img/bankwire.png" alt="1"/>
                    <fondy>{$this_description_pl}</fondy>
                </a>
            </p>
        </div>
    </div>
{/if}