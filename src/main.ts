// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { createApp } from 'vue'
import AppHub from './AppHub.vue'
import './management.css'
import './board.css'
import './delivery.css'
const root=document.getElementById('communications-vue')
if (root) createApp(AppHub).mount(root)
