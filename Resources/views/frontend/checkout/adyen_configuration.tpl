{if $sAdyenConfig}
    <div data-shopLocale='{$sAdyenConfig.shopLocale}'
         data-adyenClientKey='{$sAdyenConfig.clientKey}'
         data-adyenEnvironment='{$sAdyenConfig.environment}'
         data-enrichedPaymentMethods='{$sAdyenConfig.enrichedPaymentMethods}'
         {if $mAdyenSnippets}data-adyensnippets="{$mAdyenSnippets}"{/if}
         class="adyen-payment-selection adyen-config">
    </div>
{/if}
