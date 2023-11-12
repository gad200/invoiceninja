var a=Object.defineProperty;var d=(n,e,t)=>e in n?a(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var r=(n,e,t)=>(d(n,typeof e!="symbol"?e+"":e,t),t);/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */class o{constructor(e){r(this,"handleAuthorization",()=>{var e=$("#my-card"),t={api_login_id:this.apiLoginId,card_number:e.CardJs("cardNumber").replace(/[^\d]/g,""),expire_year:e.CardJs("expiryYear").replace(/[^\d]/g,""),expire_month:e.CardJs("expiryMonth").replace(/[^\d]/g,""),cvv:document.getElementById("cvv").value.replace(/[^\d]/g,"")};return document.getElementById("pay-now")&&(document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden")),forte.createToken(t).success(this.successResponseHandler).error(this.failedResponseHandler),!1});r(this,"successResponseHandler",e=>(document.getElementById("payment_token").value=e.onetime_token,document.getElementById("card_brand").value=e.card_type,document.getElementById("server_response").submit(),!1));r(this,"failedResponseHandler",e=>{var t='<div class="alert alert-failure mb-4"><ul><li>'+e.response_description+"</li></ul></div>";return document.getElementById("forte_errors").innerHTML=t,document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden"),!1});r(this,"handle",()=>{let e=document.getElementById("pay-now");return e&&e.addEventListener("click",t=>{this.handleAuthorization()}),this});this.apiLoginId=e,this.cardHolderName=document.getElementById("cardholder_name")}}const s=document.querySelector('meta[name="forte-api-login-id"]').content;new o(s).handle();