/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { ClaimedPresentation } from './types/communications'

export interface WorkflowOperatorRegistration {
  id: string
  operation: string
  color: string
  element: string
}

declare global {
  interface Window {
    oc_requesttoken?: string
    __contextualCampaignInitial?: ClaimedPresentation
    OC?: {
      webroot?: string
      requestToken?: string
      getLocale?: () => string
    }
    OCA?: {
      WorkflowEngine?: {
        registerOperator?: (operator: WorkflowOperatorRegistration) => void
      }
    }
  }
}
