// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
/* SPDX-License-Identifier: AGPL-3.0-or-later */
import { defineCustomElement } from 'vue'
import FlowCampaignPicker from './components/FlowCampaignPicker.vue'

const tag='librecode-contextual-campaign-picker'
if (!window.customElements.get(tag)) {
  window.customElements.define(tag,defineCustomElement(FlowCampaignPicker,{shadowRoot:false}))
}
function register() {
  const api = window.OCA?.WorkflowEngine
  if(!api?.registerOperator)return
  api.registerOperator({
    id:'OCA\\Communications\\Service\\FlowCampaignOperation',
    operation:'',
    color: 'var(--color-primary-element)',
    element:tag,
  })
}
if (document.readyState==='loading') {
  document.addEventListener('DOMContentLoaded',register,{once:true})
} else {
  register()
}
