// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
// A stable, explicit icon catalog; only paths from @mdi/js are rendered.
// IDs are compatible with the names offered in Announcement Banner 2.8.1.
// Do not load SVG, CSS, JavaScript or network assets from campaign records.
import {
 mdiInformation, mdiInformationOutline, mdiCheckboxMarkedCircle, mdiAlert,
 mdiAlertDecagram, mdiAlertCircle, mdiAlertOctagon, mdiBellOutline, mdiBellRingOutline,
 mdiBullhornOutline, mdiCalendarBlankOutline, mdiCalendarClock, mdiClockAlertOutline,
 mdiShieldCheckOutline, mdiShieldAlertOutline, mdiStarOutline, mdiLock, mdiWrench,
 mdiProgressWrench, mdiBroom, mdiServer, mdiServerOff, mdiDatabase, mdiCloud,
 mdiCloudAlert, mdiWifiOff, mdiSync, mdiUpdate, mdiDownload, mdiUpload, mdiEmail,
 mdiFlag, mdiGift, mdiPartyPopper, mdiRocketLaunch, mdiNewBox, mdiLightningBolt,
 mdiHelpCircle, mdiCheckCircle, mdiCheckDecagram, mdiCloseCircle, mdiCloseOctagon,
} from '@mdi/js'
import { t } from '@nextcloud/l10n'

export const DEFAULT_BANNER_COLOR = '#6748AC'
export const BANNER_COLOR_PALETTE = [
  {color:'#6748AC',name:t('communications', 'Purple')},
  {color:'#0B78B9',name:t('communications', 'Blue')},
  {color:'#157347',name:t('communications', 'Green')},
  {color:'#B65E0A',name:t('communications', 'Orange')},
  {color:'#B33045',name:t('communications', 'Red')},
  {color:'#596579',name:t('communications', 'Gray')},
]
// Own labeling, native MDI vector paths, ID parity with the reference app.
const ICONS: ReadonlyArray<readonly [string, string, string]> = Object.freeze([
 ['megaphone',t('communications', 'Megaphone'),mdiBullhornOutline],
 ['bullhorn',t('communications', 'Loudspeaker'),mdiBullhornOutline],
 ['bell',t('communications', 'Bell'),mdiBellOutline],
 ['bell-ring',t('communications', 'Ringing bell'),mdiBellRingOutline],
 ['alert',t('communications', 'Alert'),mdiAlert],
 ['alert-circle',t('communications', 'Circular alert'),mdiAlertCircle],
 ['alert-octagon',t('communications', 'Octagonal alert'),mdiAlertOctagon],
 ['information',t('communications', 'Information'),mdiInformation],
 ['information-outline',t('communications', 'Outlined information'),mdiInformationOutline],
 ['check-circle',t('communications', 'Confirmation'),mdiCheckCircle],
 ['check-decagram',t('communications', 'Confirmation seal'),mdiCheckDecagram],
 ['close-circle',t('communications', 'Circular error'),mdiCloseCircle],
 ['close-octagon',t('communications', 'Octagonal error'),mdiCloseOctagon],
 ['shield-alert',t('communications', 'Warning shield'),mdiShieldAlertOutline],
 ['shield-check',t('communications', 'Verified shield'),mdiShieldCheckOutline],
 ['lock',t('communications', 'Lock'),mdiLock],
 ['wrench',t('communications', 'Tool'),mdiWrench],
 ['progress-wrench',t('communications', 'Maintenance'),mdiProgressWrench],
 ['broom',t('communications', 'Cleanup'),mdiBroom],
 ['server',t('communications', 'Server'),mdiServer],
 ['server-off',t('communications', 'Server unavailable'),mdiServerOff],
 ['database',t('communications', 'Database'),mdiDatabase],
 ['cloud',t('communications', 'Cloud'),mdiCloud],
 ['cloud-alert',t('communications', 'Cloud warning'),mdiCloudAlert],
 ['wifi-off',t('communications', 'Network disconnected'),mdiWifiOff],
 ['sync',t('communications', 'Synchronization'),mdiSync],
 ['update',t('communications', 'Update'),mdiUpdate],
 ['download',t('communications', 'Download'),mdiDownload],
 ['upload',t('communications', 'Upload'),mdiUpload],
 ['calendar',t('communications', 'Calendar'),mdiCalendarBlankOutline],
 ['calendar-clock',t('communications', 'Date and time'),mdiCalendarClock],
 ['clock-alert',t('communications', 'Urgent deadline'),mdiClockAlertOutline],
 ['email',t('communications', 'Email'),mdiEmail],
 ['flag',t('communications', 'Flag'),mdiFlag],
 ['star',t('communications', 'Star'),mdiStarOutline],
 ['gift',t('communications', 'Gift'),mdiGift],
 ['party-popper',t('communications', 'Celebration'),mdiPartyPopper],
 ['rocket-launch',t('communications', 'Launch'),mdiRocketLaunch],
 ['new-box',t('communications', 'New'),mdiNewBox],
 ['lightning-bolt',t('communications', 'Energy'),mdiLightningBolt],
 ['help-circle',t('communications', 'Help'),mdiHelpCircle],
 ['info',t('communications', 'Information (legacy)'),mdiInformation],
 ['success',t('communications', 'Success (legacy)'),mdiCheckboxMarkedCircle],
 ['warning',t('communications', 'Warning (legacy)'),mdiAlert],
 ['error',t('communications', 'Error (legacy)'),mdiAlertDecagram],
 ['shield',t('communications', 'Shield (legacy)'),mdiShieldCheckOutline],
])
const ICON_PATHS: Readonly<Record<string, string>> = Object.freeze(Object.fromEntries(ICONS.map(([id, _label, path]) => [id, path])))
export const BANNER_ICON_OPTIONS = [
  {value:'auto',label:t('communications', 'Icon based on message type')},
  {value:'none',label:t('communications', 'No icon')},
  ...ICONS.map(([value,label,path])=>({value,label,path})),
]
const SEMANTIC_TYPES = new Set(['info','success','warning','error'])

export interface BannerAppearanceInput {
  theme?: unknown
  banner_icon?: unknown
  banner_color?: unknown
}

export interface BannerAppearanceResult {
  type: 'info' | 'success' | 'warning' | 'error'
  noteClass: Record<string, boolean>
  style: Record<string, string>
  hideIcon: boolean
  iconPath: string | null
}

export function bannerAppearance(campaign: BannerAppearanceInput = {}): BannerAppearanceResult {
  const theme = typeof campaign.theme==='string' ? campaign.theme : 'default'
  const nativeType = (SEMANTIC_TYPES.has(theme) ? theme : 'info') as BannerAppearanceResult['type']
  const choice = typeof campaign.banner_icon === 'string' && BANNER_ICON_OPTIONS.some(i => i.value === campaign.banner_icon)
    ? campaign.banner_icon : 'auto'
  const rawColor = typeof campaign.banner_color==='string' ? campaign.banner_color : ''
  const color = /^#[0-9a-fA-F]{3}$/.test(rawColor)
    ? '#' + [...rawColor.slice(1)].map(char=>char+char).join('')
    : /^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/.test(rawColor) ? rawColor : DEFAULT_BANNER_COLOR
  const noIcon = choice==='none' || (choice==='auto' && (theme==='neutral'||theme==='custom'))
  const background = 'color-mix(in srgb, '+color+' 22%, var(--color-main-background))'
  const accent = 'color-mix(in srgb, '+color+' 45%, var(--color-main-text) 55%)'
  const style = (theme === 'neutral' ? {
    '--note-background':'var(--color-main-background)',
    '--note-theme':'var(--color-border-dark, var(--color-border))',
    'background-color':'var(--color-main-background) !important',
    'border-inline-start':'2px solid var(--color-border) !important',
  } : theme==='custom' ? {
    '--cc-selected-color':color,
    '--note-background':background,
    '--note-theme':accent,
    'background-color':background+' !important',
  } : {}) as Record<string, string>
  return {
    type:nativeType,
    noteClass:{
      'cc-note-neutral':theme==='neutral',
      'cc-note-custom':theme==='custom',
      'cc-note-no-icon':noIcon,
    },
    style,
    hideIcon:noIcon,
    iconPath:choice!=='auto'&&choice!=='none' ? ICON_PATHS[choice] : null,
  }
}
