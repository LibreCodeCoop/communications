// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { createApp } from 'vue'
import CampaignDelivery from './CampaignDelivery.vue'
import './delivery.css'

function mount() {
  const root = document.createElement('div')
  root.id = 'communications-delivery'
  document.body.appendChild(root)
  createApp(CampaignDelivery).mount(root)
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount, { once: true })
} else {
  mount()
}
