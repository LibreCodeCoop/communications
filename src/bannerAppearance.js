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
import './bannerAppearance.css'

export const DEFAULT_BANNER_COLOR = '#6748AC'
export const BANNER_COLOR_PALETTE = [
  {color:'#6748AC',name:'Roxo'},
  {color:'#0B78B9',name:'Azul'},
  {color:'#157347',name:'Verde'},
  {color:'#B65E0A',name:'Laranja'},
  {color:'#B33045',name:'Vermelho'},
  {color:'#596579',name:'Cinza'},
]
// Own labeling, native MDI vector paths, ID parity with the reference app.
const ICONS = Object.freeze([
 ['megaphone','Megafone',mdiBullhornOutline],
 ['bullhorn','Alto-falante',mdiBullhornOutline],
 ['bell','Sino',mdiBellOutline],
 ['bell-ring','Sino tocando',mdiBellRingOutline],
 ['alert','Alerta',mdiAlert],
 ['alert-circle','Alerta circular',mdiAlertCircle],
 ['alert-octagon','Alerta octogonal',mdiAlertOctagon],
 ['information','Informação',mdiInformation],
 ['information-outline','Informação contornada',mdiInformationOutline],
 ['check-circle','Confirmação',mdiCheckCircle],
 ['check-decagram','Selo de confirmação',mdiCheckDecagram],
 ['close-circle','Erro circular',mdiCloseCircle],
 ['close-octagon','Erro octogonal',mdiCloseOctagon],
 ['shield-alert','Escudo de alerta',mdiShieldAlertOutline],
 ['shield-check','Escudo com confirmação',mdiShieldCheckOutline],
 ['lock','Cadeado',mdiLock],
 ['wrench','Ferramenta',mdiWrench],
 ['progress-wrench','Manutenção',mdiProgressWrench],
 ['broom','Limpeza',mdiBroom],
 ['server','Servidor',mdiServer],
 ['server-off','Servidor indisponível',mdiServerOff],
 ['database','Banco de dados',mdiDatabase],
 ['cloud','Nuvem',mdiCloud],
 ['cloud-alert','Alerta da nuvem',mdiCloudAlert],
 ['wifi-off','Rede desconectada',mdiWifiOff],
 ['sync','Sincronização',mdiSync],
 ['update','Atualização',mdiUpdate],
 ['download','Baixar',mdiDownload],
 ['upload','Enviar',mdiUpload],
 ['calendar','Calendário',mdiCalendarBlankOutline],
 ['calendar-clock','Data e hora',mdiCalendarClock],
 ['clock-alert','Prazo urgente',mdiClockAlertOutline],
 ['email','E-mail',mdiEmail],
 ['flag','Bandeira',mdiFlag],
 ['star','Estrela',mdiStarOutline],
 ['gift','Presente',mdiGift],
 ['party-popper','Celebração',mdiPartyPopper],
 ['rocket-launch','Lançamento',mdiRocketLaunch],
 ['new-box','Novidade',mdiNewBox],
 ['lightning-bolt','Energia',mdiLightningBolt],
 ['help-circle','Ajuda',mdiHelpCircle],
 ['info','Informação (legado)',mdiInformation],
 ['success','Sucesso (legado)',mdiCheckboxMarkedCircle],
 ['warning','Aviso (legado)',mdiAlert],
 ['error','Erro (legado)',mdiAlertDecagram],
 ['shield','Escudo (legado)',mdiShieldCheckOutline],
])
const ICON_PATHS = Object.freeze(Object.fromEntries(ICONS.map(([id,label,path])=>[id,path])))
export const BANNER_ICON_OPTIONS = [
  {value:'auto',label:'Ícone de acordo com o tipo de mensagem'},
  {value:'none',label:'Sem ícone'},
  ...ICONS.map(([value,label,path])=>({value,label,path})),
]
const SEMANTIC_TYPES = new Set(['info','success','warning','error'])

export function bannerAppearance(campaign={}) {
  const theme = typeof campaign.theme==='string' ? campaign.theme : 'default'
  const nativeType = SEMANTIC_TYPES.has(theme) ? theme : 'info'
  const choice = BANNER_ICON_OPTIONS.some(i=>i.value===campaign.banner_icon)
    ? campaign.banner_icon : 'auto'
  const rawColor = typeof campaign.banner_color==='string' ? campaign.banner_color : ''
  const color = /^#[0-9a-fA-F]{3}$/.test(rawColor)
    ? '#' + [...rawColor.slice(1)].map(char=>char+char).join('')
    : /^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/.test(rawColor) ? rawColor : DEFAULT_BANNER_COLOR
  const noIcon = choice==='none' || (choice==='auto' && (theme==='neutral'||theme==='custom'))
  const background = 'color-mix(in srgb, '+color+' 22%, var(--color-main-background))'
  const accent = 'color-mix(in srgb, '+color+' 45%, var(--color-main-text) 55%)'
  const style = theme==='neutral' ? {
    '--note-background':'var(--color-main-background)',
    '--note-theme':'var(--color-border-dark, var(--color-border))',
    'background-color':'var(--color-main-background) !important',
    'border-inline-start':'2px solid var(--color-border) !important',
  } : theme==='custom' ? {
    '--cc-selected-color':color,
    '--note-background':background,
    '--note-theme':accent,
    'background-color':background+' !important',
  } : {}
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
