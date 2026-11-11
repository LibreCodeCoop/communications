<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
import { t, getLanguage } from '@nextcloud/l10n'

import { computed, onMounted, ref, watch } from 'vue'
const props = defineProps({ createRequest: { type:Number, default:0 } })
const emit=defineEmits(['editor-view'])
watch(() => props.createRequest, (value,previous) => { if(value!==previous) openCreate() })
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import MarkdownEditor from './components/MarkdownEditor.vue'
import CampaignPresentation from './components/CampaignPresentation.vue'
import AutomationBuilder from './components/AutomationBuilder.vue'
import DeliveryChannelOption from './components/DeliveryChannelOption.vue'
import { bannerAppearance, BANNER_COLOR_PALETTE, BANNER_ICON_OPTIONS, DEFAULT_BANNER_COLOR } from './bannerAppearance.js'
import {
  mdiEyeOutline, mdiContentCopy, mdiArchiveOutline, mdiPlayCircleOutline,
  mdiPauseCircleOutline, mdiBackupRestore, mdiChartBar,
  mdiBullhornOutline, mdiAccountGroupOutline, mdiMapMarkerOutline,
  mdiRepeat, mdiCalendarRange, mdiLightningBoltOutline,
  mdiApps, mdiMessageTextOutline, mdiCheckCircleOutline, mdiWindowMaximize, mdiDockTop,
  mdiInformation, mdiCheckboxMarkedCircle, mdiAlert, mdiAlertDecagram,
  mdiPaletteOutline, mdiMinusCircleOutline, mdiArrowUp, mdiArrowDown,
} from '@mdi/js'

const BASE = (window.OC?.webroot || '') + '/index.php/apps/communications'
const statuses = [
  { label: t('communications', 'All statuses'), value: 'all' },
  { label: t('communications', 'Active'), value: 'active' },
  { label: t('communications', 'Scheduled'), value: 'scheduled' },
  { label: t('communications', 'Ended'), value: 'ended' },
  { label: t('communications', 'Drafts'), value: 'draft' },
  { label: t('communications', 'Paused'), value: 'paused' },
  { label: t('communications', 'Archived'), value: 'archived' },
]
const audienceOptions = [
  { label: t('communications', 'All signed-in users'), value: 'all' },
  { label: t('communications', 'Administrators only'), value: 'admins' },
  { label: t('communications', 'One or more Nextcloud groups'), value: 'group' },
  { label: t('communications', 'Specific users'), value: 'users' },
]
const publishOptions = [
  { label: t('communications', 'Draft (do not publish or send)'), value: 'draft' },
  { label: t('communications', 'Active (publish and send when eligible)'), value: 'active' },
  { label: t('communications', 'Paused (do not display or send)'), value: 'paused' },
]
const placementOptions = computed(() => (catalog.value.automation?.placements || [
  {label:t('communications', 'Any supported page'),value:'any'},
  {label:'Files',value:'files'},
  {label:'Dashboard',value:'dashboard'},
  {label:t('communications', 'Personal settings'),value:'settings'},
  {label:t('communications', 'Administration settings'),value:'admin_settings'},
]).map(p => ({label:p.label,value:p.value})))
const targetModeOptions = [
  {value:'legacy',label:t('communications', 'A defined area (existing mode)')},
  {value:'all',label:t('communications', 'All supported applications')},
  {value:'only',label:t('communications', 'Only selected applications')},
  {value:'exclude',label:t('communications', 'All except selected applications')},
]
const targetAppOptions = computed(() => placementOptions.value.filter(p=>p.value!=='any'))
const frequencyOptions = [
  {label:t('communications', 'On each occurrence'),value:'event'},
  {label:t('communications', 'Once per publication and user'),value:'once'},
  {label:t('communications', 'At most once every 24 hours'),value:'daily'},
  {label:t('communications', 'At most once every 7 days'),value:'weekly'},
  {label:'Intervalo personalizado',value:'custom'},
]
const excludedMatchOptions = [
  {label:t('communications', 'Exclude members of any group (OR)'),value:'any'},
  {label:t('communications', 'Exclude only members of every group (AND)'),value:'all'},
]
const groupMatchOptions = [
  {label:'Qualquer grupo selecionado (OU)',value:'any'},
  {label:t('communications', 'All selected groups (AND)'),value:'all'},
]
const categoryOptions = [
  {label:t('communications', 'Institutional announcement'),value:'institutional'},
  {label:t('communications', 'Sponsored content'),value:'sponsored'},
]
const themeOptions = [
  {label:t('communications', 'Nextcloud default'),value:'default'},
  {label:'Destaque institucional',value:'primary'},
  {label:t('communications', 'Information'),value:'info'},
  {label:'Sucesso',value:'success'},
  {label:t('communications', 'Warning'),value:'warning'},
  {label:t('communications', 'Error'),value:'error'},
  {label:'Neutro',value:'neutral'},
  {label:'Personalizado',value:'custom'},
]
// Match the native NcNoteCard variants and icons used by Nextcloud.
const bannerTypes = [
  {value:'info',label:t('communications', 'Information'),icon:mdiInformation},
  {value:'success',label:'Sucesso',icon:mdiCheckboxMarkedCircle},
  {value:'warning',label:t('communications', 'Warning'),icon:mdiAlert},
  {value:'error',label:t('communications', 'Error'),icon:mdiAlertDecagram},
  {value:'neutral',label:'Neutro',icon:mdiMinusCircleOutline},
  {value:'custom',label:'Personalizado',icon:mdiPaletteOutline},
]
const selectable = option => option.value
const campaigns = ref([])
const catalog = ref({ triggers: [], groups: [], automation:{intents:[],sources:[],placements:[],applications:[]} })
const loading = ref(true)
const saving = ref(false)
const view = ref('list')
watch(view, value => emit('editor-view', value), { immediate:true })
const search = ref('')
const filterStatus = ref('all')
const editorId = ref(null)
const previewOpen = ref(false)
const wizardStep = ref(1)
const wizardSteps = [
  { number:1, label:'Quando publicar', shortLabel:'Momento', icon:mdiApps },
  { number:2, label:t('communications', 'Message and audience'), shortLabel:t('communications', 'Message'), icon:mdiMessageTextOutline },
  { number:3, label:t('communications', 'Review and publish'), shortLabel:t('communications', 'Publish'), icon:mdiCheckCircleOutline },
]
const nextStepLabel = computed(() => wizardStep.value === 1 ? t('communications', 'Continue: Message and audience') : 'Continuar: Revisar e publicar')

function checkWizardStep(step) {
  errorMessage.value = ''
  if (step === 1 && !automationReady.value) {
    errorMessage.value = t('communications', 'Choose when to deliver the publication.')
    return false
  }
  if (step === 1 && form.value.trigger_key === 'nextcloud.time.window' && (!(form.value.starts_at instanceof Date) || Number.isNaN(form.value.starts_at.getTime()))) {
    errorMessage.value = t('communications', 'Enter the scheduled start date.')
    return false
  }
  if (step === 2) {
    if (!form.value.name.trim() || !form.value.headline.trim() || !form.value.message.trim()) {
      errorMessage.value = t('communications', 'Enter the internal name, title, and message before continuing.')
      return false
    }
    if (form.value.audience_type === 'group' && selectedGroups.value.length === 0) {
      errorMessage.value = t('communications', 'Select at least one recipient group.')
      return false
    }
    if (form.value.audience_type === 'users' && selectedUsers.value.length === 0) {
      errorMessage.value = t('communications', 'Select at least one recipient user.')
      return false
    }
    if (Boolean(form.value.cta_label.trim()) !== Boolean(form.value.cta_url.trim())) {
      errorMessage.value = t('communications', 'To display a button, enter its text and URL.')
      return false
    }
    if (!hasDestination.value) {
      errorMessage.value = t('communications', 'Select at least one delivery method.')
      return false
    }

  }
  return true
}
function goToWizardStep(target) {
  if (target < 1 || target > 3) return
  if (target > wizardStep.value) {
    for (let step=wizardStep.value; step < target; step++) {
      if (!checkWizardStep(step)) return
    }
  }
  wizardStep.value = target
  errorMessage.value = ''
}
function continueWizard() {
  goToWizardStep(wizardStep.value + 1)
}
function onIntentChange({from,to}) {
  // A scheduled draft must not silently keep a future date when changed into
  // an event-driven campaign, which would otherwise never appear in tests.
  if (to === 'board' && from === null) {
    // No channel is preselected for a new immediate publication.
    form.value.presentation = 'none'
  }

  if (to !== 'schedule') form.value.guest_visible = false
  if (from === 'schedule' && to !== 'schedule') {
    form.value.starts_at = null
    form.value.ends_at = null
  }
}

const feedback = ref('')
const feedbackType = ref('info')
const errorMessage = ref('')
const confirmArchiveOpen = ref(false)
const confirmDeleteOpen = ref(false)
const deleteTarget = ref(null)
const deleteName = ref('')
const deleting = ref(false)
const archiveTarget = ref(null)
const rowMenuOpen = ref({})
const draggingCampaignId = ref(null)
const orderSaving = ref(false)
const previewCampaign = ref(null)
const editorGroupValues = ref([])
const editorConfigLoading = ref(false)
const selectedGroups = ref([])
const selectedExcludedGroups = ref([])
const selectedUsers = ref([])
const metricsOpen = ref(false)
const metricsLoading = ref(false)
const metricsData = ref(null)
const metricsCampaign = ref(null)
const userSearchOptions = ref([])
const page = ref(1)
const PAGE_SIZE = 10
let userSearchGeneration = 0
const form = ref(makeDefault())

// Outbound channels, Inbox history and presentation are independent.
const hasAdditionalNotices = computed(() =>
  !!(form.value.notify_users || form.value.notify_activity || form.value.notify_email
    || (form.value.gateway_channels || []).length))
const eventDrivenDelivery = computed(() =>
  !!form.value.trigger_key
    && !['nextcloud.board.published','nextcloud.time.window'].includes(form.value.trigger_key))
const hasDestination = computed(() =>
  ['banner','modal'].includes(form.value.presentation) || inboxCopy.value || hasAdditionalNotices.value)
const destinationCount = computed(() =>
  (['banner','modal'].includes(form.value.presentation) ? 1 : 0)
    + (inboxCopy.value ? 1 : 0)
    + [form.value.notify_users,form.value.notify_activity,form.value.notify_email].filter(Boolean).length
    + (form.value.gateway_channels || []).length)
const inboxCopy = computed({
  get:() => !!form.value.board_visible,
  set:enabled => {
    if (form.value.guest_visible) return
    form.value.board_visible = !!enabled
  },
})
const deliveryChannelDefinitions = [
  {id:'notifications',field:'notify_users',label:t('communications', 'Nextcloud Notifications'),
    description:t('communications', 'Notification in the bell menu. Requires the Notifications app.')},
  {id:'activity',field:'notify_activity',label:'Feed de atividades (Activity)',
    description:t('communications', 'Records the publication in the Activity feed without showing a banner or dialog.')},
  {id:'email',field:'notify_email',label:'E-mail',
    description:t('communications', 'Sends the message to recipients with valid addresses.')},
]
const gatewayDefinitions = [
  {id:'gateway:sms',label:'SMS',description:'Telefone internacional, ex.: +5521999999999.'},
  {id:'gateway:signal',label:'Signal',description:t('communications', 'Signal number, e.g. +5521999999999.')},
  {id:'gateway:telegram',label:'Telegram',description:'Identificador de chat privado com o bot.'},
  {id:'gateway:gowhatsapp',label:'WhatsApp',description:'Telefone com WhatsApp, em formato internacional.'},
  {id:'gateway:whatsappbusiness',label:'WhatsApp Business',description:t('communications', 'Subject to the provider\'s policies and approved templates.')},
  {id:'gateway:xmpp',label:'XMPP',description:t('communications', 'Recipient JID address.')},
]
const gatewayOptions = computed(() =>
  gatewayDefinitions.map(definition => {
    const meta=(catalog.value.deliveryChannels || []).find(item=>item.id===definition.id)
    const gatewayId=definition.id.slice(8)
    const selected=(form.value.gateway_channels||[]).includes(gatewayId)
    const ready=meta?.ready===true
    const selectable=(!form.value.guest_visible && ready)
      || (!form.value.guest_visible && selected)
    const availability=form.value.guest_visible ? 'guest' : (meta?.availability || 'loading')
    const status=form.value.guest_visible ? t('communications', 'No recipients')
      : selected && !ready ? t('communications', 'Selected · unavailable') : (meta?.status||t('communications', 'Checking…'))
    const help=form.value.guest_visible
      ? t('communications', 'Anonymous public banners cannot send individual messages.')
      : (meta?.help || 'Verificando disponibilidade do provedor.')
    return {...definition,gatewayId,selected,ready,selectable,availability,status,help}
  })
)
const gatewayGroup = computed(() =>
  (catalog.value.deliveryChannels||[]).find(item=>item.id==='twofactor_gateway')
)
const gatewayReadyCount = computed(() => gatewayOptions.value.filter(x=>x.ready).length)

const deliveryChannelOptions = computed(() =>
  deliveryChannelDefinitions.map(definition => {
    const meta=(catalog.value.deliveryChannels || []).find(item=>item.id===definition.id)
    const selected=definition.field !== null && !!form.value[definition.field]
    const ready=meta?.ready === true && definition.field !== null
    const usable=ready && !form.value.guest_visible
    const selectable=usable || (selected && !form.value.guest_visible)
    const availability=form.value.guest_visible ? 'guest' : (meta?.availability||'loading')
    let status=form.value.guest_visible ? t('communications', 'No individual delivery')
      : (meta?.status||t('communications', 'Checking…'))
    if (selected && !ready && !form.value.guest_visible) status=t('communications', 'Selected · unavailable')
    const help=form.value.guest_visible
      ? t('communications', 'Anonymous public banners do not send individual messages. Disable guest mode to configure recipients and channels.')
      : (meta?.help || t('communications', 'Availability of this channel is still being checked.'))
    return {...definition, selected, ready, selectable, availability, status, help}
  })
)
function setNoticeChannel(field,enabled) {
  if (form.value.guest_visible) return
  const channel=deliveryChannelDefinitions.find(item=>item.field===field)
  if (!channel) return
  const ready=(catalog.value.deliveryChannels || []).some(item=>item.id===channel.id && item.ready===true)
  // A previously selected channel must remain removable when its app disappears.
  if (enabled && !ready) return
  form.value[field] = !!enabled
}
function onDeliveryChannelChange({id,enabled}) {
  if (id==='banner' || id==='modal') {
    if (enabled) {
      // First visual destination on an immediate campaign defaults to a
      // single impression per user, not daily surprise modals/banners.
      if (form.value.trigger_key==='nextcloud.board.published'
          && form.value.presentation==='none' && form.value.frequency==='event') {
        form.value.frequency='once'
      }
      form.value.presentation=id
    } else if (form.value.presentation===id) {
      form.value.presentation='none'
    }
    if (id!=='banner' || !enabled) form.value.guest_visible=false
    return
  }
  if (id==='inbox') {
    inboxCopy.value=enabled
    return
  }
  if (id.startsWith('gateway:')) {
    const gateway=id.slice(8)
    if (!gatewayDefinitions.some(item=>item.gatewayId===gateway || item.id===id)) return
    const selected=[...(form.value.gateway_channels||[])]
    const ready=(catalog.value.deliveryChannels||[]).some(item=>item.id===id && item.ready===true)
    if (enabled && (form.value.guest_visible || !ready)) return
    form.value.gateway_channels=enabled ? [...new Set([...selected,gateway])]
      : selected.filter(item=>item!==gateway)
    return
  }
  const channel=deliveryChannelDefinitions.find(item=>item.id===id)
  if (channel?.field) setNoticeChannel(channel.field,enabled)
}
const noticeUsers = computed({
  get:() => !!form.value.notify_users,
  set:enabled => setNoticeChannel('notify_users',enabled),
})
const noticeActivity = computed({
  get:() => !!form.value.notify_activity,
  set:enabled => setNoticeChannel('notify_activity',enabled),
})
const noticeEmail = computed({
  get:() => !!form.value.notify_email,
  set:enabled => setNoticeChannel('notify_email',enabled),
})
// All destinations use the same channel-style switch. Only these two
// visual destinations are mutually exclusive, enforced by presentation.
const visualDeliveryOptions = computed(() => [
  {
    id:'banner',label:'Banner contextual',
    description:t('communications', 'Non-intrusive notice on the page during the next eligible visit or event.'),
    selected:form.value.presentation==='banner',selectable:true,
    availability:'ready',status:form.value.presentation==='banner'?'Selecionado':t('communications', 'Available'),
    help:t('communications', 'The banner appears during navigation. Selecting it disables the modal but keeps other channels.')
  },
  {
    id:'modal',label:'Janela modal',
    description:t('communications', 'Dialog that requires interaction on the next eligible visit or event.'),
    selected:form.value.presentation==='modal',selectable:!form.value.guest_visible,
    availability:form.value.guest_visible?'guest':'ready',
    status:form.value.guest_visible?t('communications', 'Not available to guests'):form.value.presentation==='modal'?'Selecionado':t('communications', 'Available'),
    help:t('communications', 'Modal and banner are alternatives. Selecting one disables the other; email, history, and notifications remain.')
  },
])
const inboxDeliveryOption = computed(() => ({
  id:'inbox',label:t('communications', 'Inbox'),
  description:t('communications', 'Keeps the publication available in Nextcloud, with optional comments.'),
  selected:inboxCopy.value,selectable:!form.value.guest_visible,
  availability:form.value.guest_visible?'guest':'ready',
  status:form.value.guest_visible?t('communications', 'Not available to guests'):inboxCopy.value?'Selecionado':t('communications', 'Available'),
  help:t('communications', 'Inbox is an independent destination. If disabled, messages sent through other channels open only their authorized details.')
}))

const deliveryReview = computed(() => {
  const surfaces = []
  if (form.value.presentation === 'banner') surfaces.push('Banner contextual')
  if (form.value.presentation === 'modal') surfaces.push('Janela modal')
  if (inboxCopy.value) surfaces.push(t('communications', 'Inbox'))

  if (form.value.notify_users) surfaces.push(t('communications', 'Nextcloud Notifications'))
  if (form.value.notify_activity) surfaces.push('Feed de atividades')
  if (form.value.notify_email) surfaces.push('E-mail')
  for(const id of (form.value.gateway_channels||[])){
    const gateway=gatewayDefinitions.find(item=>item.id==='gateway:'+id)
    if(gateway) surfaces.push(gateway.label+' (Two-Factor Gateway)')
  }
  return surfaces.join(' · ') || t('communications', 'No destination selected')
})
// Legacy default/primary become informational banners without migrating data.
const selectedBannerType = computed(() => bannerTypes.some(item => item.value === form.value.theme)
  ? form.value.theme : 'info')
const bannerPreviewAppearance = computed(() => bannerAppearance(form.value))
const bannerIconOptions = BANNER_ICON_OPTIONS.map(({ value, label }) => ({ value, label }))
const languageOptions = [
 {value:'pt_BR',label:t('communications', 'Portuguese (Brazil)')},
 {value:'en_US',label:t('communications', 'English (United States)')},
 {value:'es_ES',label:'Espanhol'},
 {value:'fr_FR',label:t('communications', 'French')},
 {value:'de_DE',label:t('communications', 'German')},
 {value:'it_IT',label:'Italiano'},
 {value:'ar',label:t('communications', 'Arabic (RTL)')},
]
const translationLocale = ref('en_US')
function translatedField(field) {
 return computed({
  get:()=>form.value.translated_content?.[translationLocale.value]?.[field] || '',
  set:value=>{
   form.value.translated_content = {
    ...(form.value.translated_content || {}),
    [translationLocale.value]:{
      ...(form.value.translated_content?.[translationLocale.value] || {}),
      [field]:value || '',
    },
   }
  },
 })
}
const translatedHeadline = translatedField('headline')
const translatedMessage = translatedField('message')
const translatedCta = translatedField('cta_label')
const selectedTextColor = computed({
 get:()=>form.value.text_color || undefined,
 set:value=>{form.value.text_color = value || ''},
})
const alignments = [
  {value:'left',label:t('communications', 'Left')}, {value:'center',label:'Centralizado'}, {value:'right',label:t('communications', 'Right')},
]
const automationReady = computed(() => {
  const draft = form.value
  if (!draft.trigger_key) return false
  if (draft.trigger_key === 'nextcloud.board.published') return true
  if (draft.trigger_key === 'nextcloud.time.window') {
    return draft.starts_at instanceof Date && !Number.isNaN(draft.starts_at.getTime())
      && (!(draft.ends_at instanceof Date) || draft.ends_at > draft.starts_at)
  }
  if (draft.trigger_key === 'nextcloud.page.opened') {
    const validApp=(catalog.value.automation?.applications || []).some(app => app.id === draft.placement && app.canPages)
    const validFilters=Object.values(draft.page_filters||{}).every(rule =>
      rule && ['equals','prefix','digits'].includes(rule.operator)
        && (rule.operator === 'digits' || /^[A-Za-z0-9._~-]{1,96}$/.test(rule.value||'')))
    return validApp && validFilters
  }
  return (catalog.value.automation?.sources || []).some(source =>
    source.actions?.some(action => action.key === draft.trigger_key))
})
const communicationReady = computed(() => {
  const draft = form.value
  return hasDestination.value && !!draft.name.trim() && !!draft.headline.trim() && !!draft.message.trim()
    && (draft.audience_type !== 'group' || selectedGroups.value.length > 0)
    && (draft.audience_type !== 'users' || selectedUsers.value.length > 0)
    && Boolean(draft.cta_label.trim()) === Boolean(draft.cta_url.trim())
})
const canContinue = computed(() => wizardStep.value === 1 ? automationReady.value : communicationReady.value)
const initialFormSnapshot = ref('')
const contentFormattingHelp = t('communications', 'Markdown with formatting and links. Custom HTML and CSS are not executed.')
const formDirty = computed(() => JSON.stringify(serializableForm()) !== initialFormSnapshot.value)
const editMode = computed(() => editorId.value !== null)

function makeDefault() {
  return {
    name: '', description: '', headline: '', message: '', cta_label: '', cta_url: '',
    trigger_key: '', page_route:'', page_filters:{}, audience_type: 'all', audience_group: '', audience_groups: [], audience_users: [],
    presentation: 'banner', status: 'draft', priority: 10,
    placement:'any', app_target_mode:'legacy', app_target_apps:[], frequency:'event', min_interval_minutes:0,
    max_total:0, max_per_day:0, category:'institutional',
    theme:'default', banner_color:DEFAULT_BANNER_COLOR, banner_icon:'auto',
    board_visible:false, guest_visible:false, comments_enabled:true, dismissible:true,
    notify_users:false, notify_activity:false, notify_email:false, gateway_channels:[], text_align:'left',
    text_color:'', manual_order:0, translated_content:{},
    group_match:'any', excluded_match:'any', excluded_groups:[],
    starts_at: null, ends_at: null,
  }
}

async function api(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    headers.requesttoken = window.OC?.requestToken || window.oc_requesttoken || ''
  }
  const response = await fetch(BASE + path, {
    method, credentials: 'same-origin', headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  let payload
  try { payload = await response.json() } catch { payload = {} }
  if (!response.ok) throw new Error(payload.error || payload.message || t('communications', 'HTTP failure ') + response.status)
  return payload
}

/**
 * Instant cross-tab refresh without broadcasting campaign content or targeting.
 * The lightweight page adapter re-checks eligibility with the server.
 */
function announceChanges() {
  try {
    if (typeof BroadcastChannel!=='undefined') {
      const channel=new BroadcastChannel('communications')
      channel.postMessage({event:'changed'})
      channel.close()
    }
    localStorage.setItem('communications-campaign-update',String(Date.now()))
  } catch (_) {
    // Storage denied by the browser; normal 30-second fallback remains.
  }
}
function flash(text, type='info') {
  feedback.value = text
  feedbackType.value = type
}
function effectiveStatus(c) {
  if (c.status !== 'active') return c.status
  const now = Math.floor(Date.now() / 1000)
  if (c.starts_at !== null && c.starts_at > now) return 'scheduled'
  if (c.ends_at !== null && c.ends_at <= now) return 'ended'
  return 'active'
}
const labels = {
  active: 'Ativa', draft: 'Rascunho', paused: 'Pausada', archived: 'Arquivada',
  scheduled: 'Agendada', ended: 'Encerrada',
}
const currentCampaigns = computed(() => campaigns.value.filter(c => c.status !== 'archived'))
const stats = computed(() => [
  { label: t('communications', 'Registered publications'), count: currentCampaigns.value.length },
  { label: t('communications', 'Active now'), count: currentCampaigns.value.filter(c => effectiveStatus(c)==='active').length },
  { label: t('communications', 'Scheduled'), count: currentCampaigns.value.filter(c => effectiveStatus(c)==='scheduled').length },
  { label: t('communications', 'Drafts'), count: currentCampaigns.value.filter(c => effectiveStatus(c)==='draft').length },
  { label: t('communications', 'Paused'), count: currentCampaigns.value.filter(c => effectiveStatus(c)==='paused').length },
])
const visible = computed(() => campaigns.value.filter(c => {
  const q = search.value.trim().toLocaleLowerCase()
  if (filterStatus.value === 'all' && c.status === 'archived') return false
  if (filterStatus.value !== 'all' && effectiveStatus(c) !== filterStatus.value) return false
  return !q || [c.name, c.headline, c.description, c.trigger_key].join(' ').toLocaleLowerCase().includes(q)
}).sort((a,b)=>((a.manual_order||0)-(b.manual_order||0)) || (b.priority-a.priority) || (b.id-a.id)))
const pageCount = computed(() => Math.max(1, Math.ceil(visible.value.length / PAGE_SIZE)))
const currentPage = computed(() => Math.min(page.value, pageCount.value))
const pageVisible = computed(() => visible.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE))
watch([search, filterStatus], () => { page.value = 1 })
const triggerOptions = computed(() => (catalog.value.triggers || []).map(t => ({ label: t.label, value: t.key })))
const groupOptions = computed(() => (catalog.value.groups || []).map(g => ({ label: g.name, value: g.id })))
const groupChoices = computed(() => groupOptions.value.map(group => ({ id:group.value, displayName:group.label, isNoUser:true })))
const userChoices = computed(() => {
  const ids = new Set()
  return [...selectedUsers.value, ...userSearchOptions.value].filter(option => {
    if (ids.has(option.id)) return false
    ids.add(option.id)
    return true
  })
})
async function searchUsers(search) {
  const generation = ++userSearchGeneration
  if (search.trim().length < 2) {
    userSearchOptions.value = []
    return
  }
  try {
    const payload = await api('/api/users?search=' + encodeURIComponent(search))
    if (generation === userSearchGeneration) {
      userSearchOptions.value = (payload.users || []).map(user => ({
        id:user.id, displayName:user.name, user:user.id,
      }))
    }
  } catch (e) { flash(e.message, 'error') }
}
function openPreview(campaign = null) {
  rowMenuOpen.value = {}
  previewCampaign.value = campaign
  previewOpen.value = true
}
function closePreview() {
  previewOpen.value = false
  previewCampaign.value = null
}


function dateDisplay(ts) {
  return ts === null ? '' : new Date(ts * 1000).toLocaleDateString(getLanguage().replace('_', '-'))
}
function period(c) {
  if (!c.starts_at && !c.ends_at) return t('communications', 'No deadline')
  if (c.starts_at && c.ends_at) return dateDisplay(c.starts_at) + ' – ' + dateDisplay(c.ends_at)
  return c.starts_at ? t('communications', 'Since ') + dateDisplay(c.starts_at) : t('communications', 'Until ') + dateDisplay(c.ends_at)
}
function groupLabel(c) {
  if (c.audience_type === 'group') {
    const ids = c.audience_groups?.length ? c.audience_groups : (c.audience_group ? [c.audience_group] : [])
    return ids.map(id => groupOptions.value.find(g => g.value === id)?.label || id).join(', ') || t('communications', 'No group')
  }
  if (c.audience_type === 'users') {
    const users = c.audience_users || []
    return users.length === 1 ? t('communications', 'User: ') + users[0] : users.length + t('communications', ' specific users')
  }
  return c.audience_type === 'admins' ? t('communications', 'Administrators only') : t('communications', 'All users')
}
function triggerLabel(c) {
  return triggerOptions.value.find(t => t.value === c.trigger_key)?.label || c.trigger_key
}
const summaryAction = computed(() => triggerOptions.value.find(t => t.value === form.value.trigger_key)?.label || t('communications', 'Select an action'))
const sourceForReview = computed(() => catalog.value.automation?.sources?.find(source =>
  source.actions?.some(action => action.key === form.value.trigger_key)))
const summaryWhen = computed(() => {
  if (!form.value.trigger_key) return t('communications', 'Not configured')
  if (form.value.trigger_key === 'nextcloud.page.opened') {
    const place=placementOptions.value.find(p=>p.value===form.value.placement)?.label||form.value.placement
    const filters=Object.entries(form.value.page_filters||{}).map(([name,rule]) =>
      '{'+name+'} '+({equals:'=',prefix:t('communications', 'starts with'),digits:t('communications', 'numeric')}[rule.operator]||rule.operator)+' '+(rule.value||'')).join('; ')
    return 'Abrir '+place+(form.value.page_route?' · '+form.value.page_route:t('communications', ' · any page'))+(filters?' · '+filters:'')
  }
  return sourceForReview.value ? sourceForReview.value.label + ' → ' + summaryAction.value : summaryAction.value
})
const audienceReview = computed(() => {
  if (form.value.audience_type === 'users') return selectedUsers.value.map(u=>u.displayName||u.id).join(', ') || t('communications', 'No user selected')
  if (form.value.audience_type === 'group') {
    const ids=selectedGroups.value.map(g=>g.displayName||g.id)
    return ids.length ? ids.join(form.value.group_match === 'all' ? t('communications', ' AND ') : t('communications', ' OR ')) : t('communications', 'No group selected')
  }
  return t('communications', 'All signed-in users')
})
const locationReview = computed(()=>form.value.presentation==='none'
  ? (inboxCopy.value?t('communications', 'Inbox only; no banner or dialog'):t('communications', 'No on-screen display'))
  : form.value.presentation==='board' ? t('communications', 'Inbox')
    : (placementOptions.value.find(x=>x.value===form.value.placement)?.label || form.value.placement))
const frequencyReview = computed(()=>{
  if (eventDrivenDelivery.value) {
    return t('communications', 'On each occurrence, respecting frequency and deduplication limits')
  }
  if (!['banner','modal'].includes(form.value.presentation)) {
    return t('communications', 'Outbound channels: one batch per activation')
  }
  const freq=frequencyOptions.find(x=>x.value===form.value.frequency)?.label || form.value.frequency
  const caps=[form.value.max_total>0 ? t('communications', 'maximum ')+form.value.max_total+t('communications', ' per user') : '',form.value.max_per_day>0 ? t('communications', '{count} in 24 hours', {count: form.value.max_per_day}) : ''].filter(Boolean)
  return caps.length ? freq+'; '+caps.join('; ') : freq
})
const scheduleReview = computed(()=>{
  const from=form.value.starts_at instanceof Date ? form.value.starts_at.toLocaleDateString(getLanguage().replace('_', '-')) : ''
  const until=form.value.ends_at instanceof Date ? form.value.ends_at.toLocaleDateString(getLanguage().replace('_', '-')) : ''
  return from||until ? t('communications', 'From ') + (from || t('communications', 'now')) + t('communications', ' until ') + (until || t('communications', 'no end date')) : t('communications', 'No date limit')
})

async function saveEditorGroups() {
  if (editorConfigLoading.value || !catalog.value.canConfigureEditors) return
  editorConfigLoading.value=true
  try {
    const data=await api('/api/editor-groups',{method:'POST',body:{groups:editorGroupValues.value}})
    editorGroupValues.value=data.groups
    flash(t('communications', 'Editorial groups updated.'), 'success')
  } catch(e) { flash(e.message,'error') }
  finally { editorConfigLoading.value=false }
}
async function refresh() {
  const data = await api('/api/campaigns')
  campaigns.value = data.campaigns || []
}
async function updateStatus(c, status) {
  try {
    await api('/api/campaigns/' + c.id + '/status', { method:'POST', body:{status} })
    await refresh()
    announceChanges()
    flash(status==='active' ? t('communications', 'Publication activated.') : 'Status atualizado.', 'success')
  } catch (e) { flash(e.message, 'error') }
}
async function openMetrics(c) {
  metricsCampaign.value = c
  metricsData.value = null
  metricsOpen.value = true
  metricsLoading.value = true
  try {
    const result = await api('/api/campaigns/'+c.id+'/metrics')
    metricsData.value = result.metrics
  } catch (e) { flash(e.message,'error') }
  finally { metricsLoading.value = false }
}
async function moveCampaign(c,direction,targetId=null) {
  if (orderSaving.value || c.status==='archived') return
  orderSaving.value=true
  try {
    await api('/api/campaigns/'+c.id+'/move', {
      method:'POST', body:{direction,target_id:targetId},
    })
    await refresh()
    announceChanges()
    flash(t('communications', 'Display order updated.'),'success')
  } catch(e) { flash(e.message,'error') }
  finally { orderSaving.value=false }
}
function startReorderDrag(event,c) {
  if (c.status==='archived' || orderSaving.value) return event.preventDefault()
  draggingCampaignId.value=c.id
  event.dataTransfer.effectAllowed='move'
  event.dataTransfer.setData('text/plain',String(c.id))
}
function endReorderDrag() { draggingCampaignId.value=null }
async function dropReorder(event,target) {
  const from=draggingCampaignId.value
  draggingCampaignId.value=null
  if (from==null || from===target.id || target.status==='archived') return
  const moving=campaigns.value.find(c=>c.id===from)
  if (!moving) return
  await moveCampaign(moving,'before',target.id)
}
async function duplicate(c) {
  try {
    const result = await api('/api/campaigns/' + c.id + '/duplicate', { method:'POST', body:{} })
    await refresh()
    announceChanges()
    openEdit(result.campaign)
    flash(t('communications', 'Copy created as a draft.'), 'success')
  } catch (e) { flash(e.message, 'error') }
}
function askDelete(c) {
  if(c.status!=='archived')return
  deleteTarget.value=c
  deleteName.value=''
  confirmDeleteOpen.value=true
}
async function deletePermanently() {
  if(deleting.value || !deleteTarget.value || deleteName.value!==deleteTarget.value.name)return
  deleting.value=true
  const id=deleteTarget.value.id
  try {
    await api('/api/campaigns/'+id+'/purge',{method:'POST',body:{confirmName:deleteName.value}})
    confirmDeleteOpen.value=false
    deleteTarget.value=null
    deleteName.value=''
    await refresh()
    announceChanges()
    flash(t('communications', 'Publication and delivery history permanently deleted.'),'success')
  } catch(e) {flash(e.message,'error')}
  finally {deleting.value=false}
}
function askArchive(c) { archiveTarget.value = c; confirmArchiveOpen.value = true }
async function archive() {
  if (!archiveTarget.value) return
  try {
    await api('/api/campaigns/' + archiveTarget.value.id, { method:'DELETE', body:{} })
    await refresh()
    announceChanges()
    flash(t('communications', 'Publication archived.'), 'success')
  } catch (e) { flash(e.message, 'error') }
  confirmArchiveOpen.value = false
  archiveTarget.value = null
}

function openCreate() {
  selectedGroups.value = []
  selectedExcludedGroups.value = []
  selectedUsers.value = []
  userSearchOptions.value = []
  form.value = makeDefault()
  initialFormSnapshot.value = JSON.stringify(serializableForm())
  editorId.value = null
  errorMessage.value = ''
  feedback.value = ''
  wizardStep.value = 1
  view.value = 'editor'
}
function openEdit(c) {
  const groupIds = c.audience_groups?.length ? c.audience_groups : (c.audience_group ? [c.audience_group] : [])
  selectedGroups.value = groupIds.map(id => {
    const label = groupChoices.value.find(option => option.id === id)?.displayName || id
    return { id, displayName:label, isNoUser:true }
  })
  selectedUsers.value = (c.audience_users || []).map(id => ({ id, displayName:id, user:id }))
  selectedExcludedGroups.value = (c.excluded_groups || []).map(id => {
    const label = groupChoices.value.find(option => option.id === id)?.displayName || id
    return {id,displayName:label,isNoUser:true}
  })
  userSearchOptions.value = []
  form.value = {
    name:c.name, description:c.description, headline:c.headline, message:c.message,
    cta_label:c.cta_label, cta_url:c.cta_url, trigger_key:c.trigger_key, page_route:c.page_route||'',
    page_filters:c.page_filters||{},
    audience_type:c.audience_type, audience_group:c.audience_group,
    audience_groups:groupIds, audience_users:c.audience_users || [],
    presentation:c.presentation==='board'?'none':c.presentation, status:c.status === 'archived' ? 'draft' : c.status,
    priority:c.priority,
    placement:c.placement || 'any', app_target_mode:c.app_target_mode || 'legacy',
    app_target_apps:c.app_target_apps || [], frequency:c.frequency || 'event',
    min_interval_minutes:c.min_interval_minutes || 0, max_total:c.max_total || 0,
    max_per_day:c.max_per_day || 0, category:c.category || 'institutional',
    theme:c.theme || 'default', banner_color:c.banner_color || DEFAULT_BANNER_COLOR,
    banner_icon:c.banner_icon || 'auto',
    board_visible:!!c.board_visible, guest_visible:!!c.guest_visible,
    comments_enabled:c.comments_enabled !== false,
    notify_users:!!c.notify_users, notify_activity:!!c.notify_activity, notify_email:!!c.notify_email,
    gateway_channels:Array.isArray(c.gateway_channels)?c.gateway_channels:[],
    dismissible:c.dismissible !== false, text_align:c.text_align || 'left',
    text_color:c.text_color || '', manual_order:c.manual_order || 0,
    translated_content:c.translated_content || {},
    group_match:c.group_match || 'any', excluded_match:c.excluded_match || 'any',
    excluded_groups:c.excluded_groups || [],
    starts_at:c.starts_at === null ? null : new Date(c.starts_at*1000),
    ends_at:c.ends_at === null ? null : new Date(c.ends_at*1000),
  }
  initialFormSnapshot.value = JSON.stringify(serializableForm())
  editorId.value = c.id
  errorMessage.value = ''
  feedback.value = ''
  wizardStep.value = 1
  view.value = 'editor'
}
function cancelEdit() {
  if (formDirty.value && !window.confirm(t('communications', 'Discard unsaved changes?'))) return
  view.value = 'list'
  errorMessage.value = ''
}
function serializableForm() {
  return {
    ...form.value,
    audience_group: selectedGroups.value[0]?.id || '',
    audience_groups: form.value.audience_type === 'group' ? selectedGroups.value.map(option => option.id) : [],
    audience_users: form.value.audience_type === 'users' ? selectedUsers.value.map(option => option.id) : [],
    excluded_groups: selectedExcludedGroups.value.map(option => option.id),
    starts_at: form.value.starts_at instanceof Date && !Number.isNaN(form.value.starts_at.getTime())
      ? Math.floor(form.value.starts_at.getTime()/1000) : null,
    ends_at: form.value.ends_at instanceof Date && !Number.isNaN(form.value.ends_at.getTime())
      ? Math.floor(form.value.ends_at.getTime()/1000) : null,
  }
}
async function save() {
  if (saving.value) return
  const data = serializableForm()
  errorMessage.value = ''
  if (!data.name.trim() || !data.headline.trim() || !data.message.trim()) {
    errorMessage.value = t('communications', 'Internal name, title, and message are required.')
    return
  }
  if (!data.trigger_key) {
    errorMessage.value = t('communications', 'Choose when to deliver the publication.')
    return
  }
  if (data.audience_type === 'group' && !data.audience_groups.length) {
    errorMessage.value = t('communications', 'Select at least one group.')
    return
  }
  if (data.audience_type === 'users' && !data.audience_users.length) {
    errorMessage.value = t('communications', 'Select at least one user.')
    return
  }
  if (['only','exclude'].includes(data.app_target_mode)
      && data.presentation!=='board' && data.trigger_key!=='nextcloud.page.opened'
      && !data.app_target_apps.length) {
    errorMessage.value=t('communications', 'Select at least one app for this display rule.')
    return
  }
  if (Boolean(data.cta_label.trim()) !== Boolean(data.cta_url.trim())) {
    errorMessage.value = t('communications', 'To display a button, enter its text and URL.')
    return
  }
  if (data.trigger_key === 'nextcloud.time.window' && data.starts_at === null) {
    errorMessage.value = t('communications', 'The schedule needs a start date.')
    return
  }
  if (data.starts_at !== null && data.ends_at !== null && data.ends_at <= data.starts_at) {
    errorMessage.value = t('communications', 'The end date must be after the start date.')
    return
  }
  if (!hasDestination.value) {
    errorMessage.value = t('communications', 'Select at least one delivery method.')
    return
  }

  if (data.guest_visible && (data.board_visible || data.notify_users
      || data.notify_activity || data.notify_email || data.gateway_channels.length)) {
    errorMessage.value = t('communications', 'Public banners cannot send individual notifications.')
    return
  }
  if (data.audience_type !== 'group') data.audience_group = ''
  saving.value = true
  try {
    const isNew = editorId.value === null
    const saved = await api(isNew ? '/api/campaigns' : '/api/campaigns/'+editorId.value, {
      method: isNew ? 'POST' : 'PUT', body:data,
    })
    await refresh()
    announceChanges()
    if (isNew && ['nextcloud.flow.matched','external.webhook'].includes(data.trigger_key)) {
      editorId.value = saved.campaign.id
      initialFormSnapshot.value = JSON.stringify(serializableForm())
      flash(data.trigger_key === 'nextcloud.flow.matched'
        ? t('communications', 'Publication saved. Configure the rule in Flow using this publication\'s name.')
        : t('communications', 'Publication saved. Use the identifier shown in the webhook example before testing.'), 'success')
    } else {
      view.value = 'list'
      flash(isNew ? t('communications', 'Publication created.') : t('communications', 'Publication updated.'), 'success')
    }
  } catch(e) { errorMessage.value = e.message } finally { saving.value = false }
}

onMounted(async () => {
  try {
    const [metadata, data] = await Promise.all([api('/api/catalog'), api('/api/campaigns')])
    catalog.value = metadata
    editorGroupValues.value = metadata.editorGroups || []
    campaigns.value = data.campaigns || []
    if (new URL(location.href).searchParams.get('view')==='new') openCreate()
  } catch (e) { flash(e.message, 'error') }
  finally { loading.value = false }
})
</script>

<template>
  <div class="cc-native-app">
    <div class="cc-native-page">
      <NcNoteCard v-if="feedback" :type="feedbackType" class="cc-native-feedback">
        {{ feedback }}
      </NcNoteCard>

      <template v-if="view === 'list'">
        <div class="cc-native-heading cc-native-heading-overview">
          <div>
            <h2>{{ t('communications', 'Overview') }}</h2>
            <p>{{ t('communications', 'Track active, scheduled, and draft publications.') }}</p>
          </div>
        </div>

        <div class="cc-native-stats">
          <section v-for="stat in stats" :key="stat.label" class="cc-native-stat">
            <span>{{ stat.label }}</span>
            <strong>{{ stat.count }}</strong>
          </section>
        </div>

        <section class="cc-native-panel">
          <div class="cc-native-list-head">
            <div><h2>{{ t('communications', 'All publications') }}</h2><p>{{ t('communications', 'Manage persistent and contextual notices in one place. Reorder by dragging or using keyboard controls.') }}</p></div>
            <div class="cc-native-filters">
              <NcTextField v-model="search" :label="t('communications', 'Search publications')" type="search" />
              <NcSelect v-model="filterStatus" :options="statuses" :reduce="selectable"
                        :clearable="false" input-id="cc-status-filter" :aria-label="t('communications', 'Filter by status')" />
            </div>
          </div>

          <NcEmptyContent v-if="!loading && !visible.length" :name="t('communications', 'No publications found')"
                          :description="t('communications', 'Create a publication or change the filters.')" />
          <div v-else class="cc-native-table-scroll">
            <table class="cc-native-table">
              <thead><tr><th>{{ t('communications', 'Publication') }}</th><th>{{ t('communications', 'Trigger') }}</th><th>{{ t('communications', 'Audience') }}</th><th>{{ t('communications', 'Period') }}</th><th>{{ t('communications', 'Status') }}</th><th>{{ t('communications', 'Actions') }}</th></tr></thead>
              <tbody>
                <tr v-for="campaign in pageVisible" :key="campaign.id"
                    :draggable="campaign.status!=='archived' && !orderSaving"
                    @dragstart="startReorderDrag($event,campaign)"
                    @dragend="endReorderDrag"
                    @dragover.prevent
                    @drop.prevent="dropReorder($event,campaign)">
                  <td>
                    <strong>{{ campaign.name }}</strong>
                    <small>{{ campaign.headline }}</small>
                  </td>
                  <td>{{ triggerLabel(campaign) }}</td>
                  <td>{{ groupLabel(campaign) }}</td>
                  <td>{{ period(campaign) }}</td>
                  <td><span class="cc-native-status" :data-status="effectiveStatus(campaign)">
                    {{ labels[effectiveStatus(campaign)] }}
                  </span></td>
                  <td class="cc-native-table-actions">
                    <div class="cc-native-row-actions">
                      <NcButton size="small" :text="t('communications', 'Edit')" @click="openEdit(campaign)" />
                      <NcActions :aria-label="t('communications', 'Actions for {name}', {name: campaign.name})" :open="rowMenuOpen[campaign.id] || false"
                                 @update:open="rowMenuOpen[campaign.id] = $event">
                        <NcActionButton v-if="campaign.status!=='archived'" :disabled="orderSaving"
                                        @click="moveCampaign(campaign,'up')">
                          <template #icon><NcIconSvgWrapper :path="mdiArrowUp" :size="20" /></template>
                          {{ t('communications', 'Move up') }}
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status!=='archived'" :disabled="orderSaving"
                                        @click="moveCampaign(campaign,'down')">
                          <template #icon><NcIconSvgWrapper :path="mdiArrowDown" :size="20" /></template>
                          {{ t('communications', 'Move down') }}
                        </NcActionButton>
                        <NcActionButton @click="openPreview(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiEyeOutline" :size="20" /></template>
                          {{ t('communications', 'Preview presentation') }}
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status !== 'archived'"
                                        @click="updateStatus(campaign, campaign.status === 'active' ? 'paused' : 'active')">
                          <template #icon><NcIconSvgWrapper :path="campaign.status === 'active' ? mdiPauseCircleOutline : mdiPlayCircleOutline" :size="20" /></template>
                          {{ campaign.status === 'active' ? t('communications', 'Pause') : t('communications', 'Activate') }}
                        </NcActionButton>
                        <NcActionButton v-else @click="updateStatus(campaign, 'draft')">
                          <template #icon><NcIconSvgWrapper :path="mdiBackupRestore" :size="20" /></template>
                          {{ t('communications', 'Restore') }}
                        </NcActionButton>
                        <NcActionButton @click="openMetrics(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiChartBar" :size="20" /></template>
                          {{ t('communications', 'Statistics') }}
                        </NcActionButton>
                        <NcActionButton @click="duplicate(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiContentCopy" :size="20" /></template>
                          {{ t('communications', 'Duplicate') }}
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status === 'archived'" @click="askDelete(campaign)">
                          {{ t('communications', 'Delete permanently') }}
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status !== 'archived'" @click="askArchive(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiArchiveOutline" :size="20" /></template>
                          {{ t('communications', 'Archive') }}
                        </NcActionButton>
                      </NcActions>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <nav v-if="pageCount > 1" class="cc-native-pagination" :aria-label="t('communications', 'Publication pages')">
            <span>{{ t('communications', 'Showing') }} {{ (currentPage - 1) * PAGE_SIZE + 1 }}–{{ Math.min(currentPage * PAGE_SIZE, visible.length) }} {{ t('communications', 'of') }} {{ visible.length }}</span>
            <NcButton :text="t('communications', 'Previous')" :disabled="currentPage <= 1" @click="page = currentPage - 1" />
            <span>{{ t('communications', 'Page') }} {{ currentPage }} {{ t('communications', 'of') }} {{ pageCount }}</span>
            <NcButton :text="t('communications', 'Next')" :disabled="currentPage >= pageCount" @click="page = currentPage + 1" />
          </nav>
        </section>
        <section class="cc-editor-settings" :aria-label="t('communications', 'Editorial settings')">
        <details v-if="catalog.canConfigureEditors" class="cc-progressive-details cc-editor-delegation">
          <summary>{{ t('communications', 'Editorial permissions') }}</summary>
          <p class="cc-native-hint">{{ t('communications', 'Authorized groups can manage publications. Only administrators can change permissions.') }}</p>
          <div class="cc-native-control">
            <label for="cc-editor-groups">{{ t('communications', 'Groups with editorial permissions') }}</label>
            <NcSelect v-model="editorGroupValues" :options="groupOptions" :reduce="selectable"
                      multiple :clearable="true" input-id="cc-editor-groups"
                      :placeholder="t('communications', 'Select editor groups')" />
          </div>
          <NcButton :text="t('communications', 'Save delegation')" variant="primary" :disabled="editorConfigLoading" @click="saveEditorGroups" />
        </details>

        </section>
        <p class="cc-native-caption">{{ t('communications', 'Browser-reported impressions are estimates, not audited reading or billing metrics.') }}</p>
      </template>

      <template v-else>
        <div class="cc-native-heading cc-native-heading-editor">
          <div class="cc-wizard-heading">
            <NcButton variant="tertiary" :text="t('communications', 'Back to publications')" @click="cancelEdit" />
            <h1>{{ editMode ? t('communications', 'Edit publication') : t('communications', 'New publication') }}</h1>
            <p>{{ t('communications', 'One message, multiple delivery methods. Configure the timing, content, and recipients.') }}</p>
          </div>
        </div>

        <nav class="cc-wizard-steps" :aria-label="t('communications', 'Publication steps')">
          <NcButton v-for="step in wizardSteps" :key="step.number"
                    :pressed="wizardStep === step.number"
                    :aria-label="step.number + ': ' + step.label"
                    :variant="wizardStep === step.number ? 'primary' : 'tertiary'"
                    :aria-current="wizardStep === step.number ? 'step' : undefined"
                    :disabled="(step.number > 1 && !automationReady) || (step.number > 2 && !communicationReady)"
                    @click="goToWizardStep(step.number)">
            <template #icon><NcIconSvgWrapper :path="step.icon" :size="18"/></template>
            <span class="cc-wizard-step-long">{{ step.number }} {{ step.label }}</span>
            <span class="cc-wizard-step-short">{{ step.number }} {{ step.shortLabel }}</span>
          </NcButton>
        </nav>

        <NcNoteCard v-if="errorMessage" type="error" class="cc-wizard-error">{{ errorMessage }}</NcNoteCard>

        <div class="cc-native-editor-layout" :class="{ 'cc-wizard-unconfigured': wizardStep === 1 }">
          <div class="cc-native-editor-fields cc-wizard-main">
            <div v-if="wizardStep === 1" class="cc-wizard-step-content">
              <p class="cc-native-hint">{{ t('communications', 'Choose when the publication becomes available. Display and notification channels are selected together in the next step.') }}</p>
              <AutomationBuilder v-model="form.trigger_key" v-model:placement="form.placement"
                                 v-model:page-route="form.page_route" v-model:page-filters="form.page_filters"
                                 :catalog="catalog.automation" :campaign-id="editorId" @intent-change="onIntentChange" />
              <section v-if="form.trigger_key === 'nextcloud.time.window'" class="cc-native-section cc-schedule-inline">
                <h3>{{ t('communications', 'Availability period') }}</h3>
                <div class="cc-native-pair">
                  <NcDateTimePickerNative v-model="form.starts_at" :label="t('communications', 'Start *')" type="datetime-local" clearable />
                  <NcDateTimePickerNative v-model="form.ends_at" :label="t('communications', 'End (optional)')" type="datetime-local" clearable />
                </div>
              </section>
            </div>

            <div v-else-if="wizardStep === 2" class="cc-wizard-step-content">
              <div class="cc-wizard-step-title">
                <h2>{{ t('communications', 'Message, audience and delivery') }}</h2>
                <p>{{ t('communications', 'Write a message, select recipients, and configure every channel in one workflow.') }}</p>
              </div>
            <section class="cc-native-section">
              <h2>{{ t('communications', 'Identification') }}</h2>
              <NcTextField v-model="form.name" :label="t('communications', 'Internal name *')" :maxlength="160"
                           :placeholder="t('communications', 'Example: Welcome notice')" />
              <NcTextArea v-model="form.description" :label="t('communications', 'Description for your team')" :maxlength="700" />
            </section>
            <section class="cc-native-section">
              <h2>{{ t('communications', 'Content') }}</h2>
              <NcTextField v-model="form.headline" :label="t('communications', 'Displayed title *')" :maxlength="180" />
              <div class="cc-native-rich">
                <MarkdownEditor v-model="form.message" :label="t('communications', 'Displayed message *')"
                                :description="contentFormattingHelp" :placeholder="t('communications', 'Write your message…')"
                                min-height="130px" max-height="410px" />
              </div>
              <details class="cc-progressive-details" :open="Boolean(form.cta_label || form.cta_url) || undefined">
                <summary>{{ t('communications', 'Add an action button (optional)') }}</summary>
                <div class="cc-native-pair">
                  <NcTextField v-model="form.cta_label" :label="t('communications', 'Button text')" :maxlength="80" />
                  <NcTextField v-model="form.cta_url" :label="t('communications', 'HTTP(S), mailto:, tel: or internal path')" :maxlength="1900" />
                </div>
              </details>
              <details class="cc-progressive-details cc-translation-options">
                <summary>{{ t('communications', 'Message translations (optional)') }}</summary>
                <p class="cc-native-hint">{{ t('communications', 'Provide a version in the recipient\'s language. Empty fields use the original text.') }}</p>
                <div class="cc-native-control">
                  <label for="cc-translation-locale">{{ t('communications', 'Translation language') }}</label>
                  <NcSelect v-model="translationLocale" :options="languageOptions"
                            :reduce="selectable" :clearable="false"
                            input-id="cc-translation-locale"/>
                </div>
                <NcTextField v-model="translatedHeadline" :label="t('communications', 'Translated title')" :maxlength="180"/>
                <NcTextArea v-model="translatedMessage" :label="t('communications', 'Translated message')" :maxlength="4000"/>
                <NcTextField v-model="translatedCta" :label="t('communications', 'Translated button')" :maxlength="80"/>
              </details>
            </section>
            <section class="cc-native-section">
              <h2>{{ t('communications', 'Recipients') }}</h2>
              <div class="cc-native-control">
                <label for="cc-audience">{{ t('communications', 'Send to') }}</label>
                <NcSelect v-model="form.audience_type" :options="audienceOptions" :reduce="selectable"
                          :clearable="false" input-id="cc-audience" />
              </div>
              <div v-if="form.audience_type === 'group'" class="cc-native-control">
                <NcSelectUsers v-model="selectedGroups" :options="groupChoices"
                               input-id="cc-group" :input-label="t('communications', 'Recipient groups')"
                               :placeholder="t('communications', 'Select one or more groups')" multiple keep-open />
                <div class="cc-native-control">
                  <label for="cc-group-match">{{ t('communications', 'Group matching') }}</label>
                  <NcSelect v-model="form.group_match" :options="groupMatchOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-group-match" />
                </div>
              </div>
              <div v-if="form.audience_type === 'users'" class="cc-native-control">
                <NcSelectUsers v-model="selectedUsers" :options="userChoices"
                               input-id="cc-users" :input-label="t('communications', 'Recipient users')"
                               :placeholder="t('communications', 'Search by name or identifier')"
                               multiple keep-open @search="searchUsers" />
                <p class="cc-native-hint">{{ t('communications', 'Enter at least two characters. Only selected users will receive the publication.') }}</p>
              </div>
              <details class="cc-progressive-details" :open="selectedExcludedGroups.length > 0 || undefined">
                <summary>{{ t('communications', 'Exclude recipient groups (optional)') }}</summary>
                <div class="cc-native-control">
                  <NcSelectUsers v-model="selectedExcludedGroups" :options="groupChoices"
                                 input-id="cc-excluded" :input-label="t('communications', 'Excluded groups')"
                                 :placeholder="t('communications', 'These groups will not receive the publication')"
                                 multiple keep-open />
                  <label for="cc-excluded-match">{{ t('communications', 'How to match excluded groups') }}</label>
                  <NcSelect v-model="form.excluded_match" :options="excludedMatchOptions"
                            :reduce="selectable" :clearable="false" input-id="cc-excluded-match"/>
                  <p class="cc-native-hint">{{ t('communications', 'Any group (OR) excludes members of at least one group. All groups (AND) excludes only members of every group. Exclusion takes precedence over inclusion.') }}</p>
                </div>
              </details>
            </section>

            <section class="cc-native-section cc-delivery-section">
              <h2>{{ t('communications', 'Delivery methods') }}</h2>
              <p class="cc-native-hint">{{ t('communications', 'Select one or more destinations. Contextual banners and modal dialogs are alternatives: selecting one disables the other. Inbox, notifications, activity, email, and messaging can be freely combined.') }}</p>
              <div class="cc-delivery-selector" role="group" :aria-label="t('communications', 'Publication delivery methods')">
                <DeliveryChannelOption v-for="channel in visualDeliveryOptions" :key="channel.id"
                                       :channel="channel" @change="onDeliveryChannelChange"/>
                <DeliveryChannelOption :channel="inboxDeliveryOption" @change="onDeliveryChannelChange"/>
                <DeliveryChannelOption v-for="channel in deliveryChannelOptions" :key="channel.id"
                                       :channel="channel" @change="onDeliveryChannelChange"/>
                <details class="cc-gateway-provider-details">
                  <summary>
                    <strong>{{ t('communications', 'Messaging and SMS (Two-Factor Gateway)') }}</strong>
                    <span>{{ gatewayGroup?.installed
                      ? gatewayReadyCount+' de '+gatewayOptions.length+' provedores configurados'
                      : t('communications', 'App not installed') }}</span>
                  </summary>
                  <p class="cc-native-hint">
                    {{ t('communications', 'WhatsApp, SMS, Signal, Telegram and XMPP are independent destinations. Each recipient must opt in and provide their address in notification preferences. Sponsored content requires additional consent.') }}
                  </p>
                  <DeliveryChannelOption v-for="channel in gatewayOptions" :key="channel.id"
                                         :channel="channel" @change="onDeliveryChannelChange"/>
                </details>
              </div>
              <div class="cc-delivery-selection-feedback" role="status" aria-live="polite">
                <strong>{{ destinationCount }} {{ destinationCount===1?'forma selecionada':'formas selecionadas' }}</strong>
                <span>{{ deliveryReview }}</span>
              </div>
              <NcCheckboxRadioSwitch v-if="inboxCopy" v-model="form.comments_enabled"
                                     type="switch" class="cc-delivery-comments">
                {{ t('communications', 'Allow Inbox comments') }}
              </NcCheckboxRadioSwitch>
              <NcNoteCard v-if="!hasDestination" type="warning" class="cc-delivery-empty-warning">
                {{ t('communications', 'Select at least one delivery method to continue.') }}
              </NcNoteCard>
              <p v-if="eventDrivenDelivery" class="cc-native-hint">
                {{ t('communications', 'Each event occurrence targets only the authorized recipient. Outbound channels are queued; a banner or modal appears on the next eligible visit. Frequency limits apply to all deliveries, with no mass dispatch on activation.') }}
              </p>
              <p v-else-if="['banner','modal'].includes(form.presentation)" class="cc-native-hint">
                {{ t('communications', 'A banner or modal appears on the next eligible visit. Other selected channels are queued once per activation, respecting the start date.') }}
              </p>
              <p v-if="noticeEmail" class="cc-native-hint">
                {{ t('communications', 'Email requires a configured server and valid address. Delivery cannot be undone.') }}
              </p>
              <p v-if="editorId!==null && form.status==='active' && hasAdditionalNotices"
                 class="cc-native-hint">
                {{ t('communications', 'Changing channels does not resend earlier messages. New events follow the active configuration, consent, availability dates, and frequency limits.') }}
              </p>
              <div v-if="form.presentation === 'banner'" class="cc-banner-type-settings">
                <div class="cc-banner-type-label">
                  <span id="cc-banner-type-label" class="cc-native-label">{{ t('communications', 'Message type') }}</span>
                  <p class="cc-native-hint">{{ t('communications', 'Use a built-in type or choose a neutral or custom appearance.') }}</p>
                </div>
                <div class="cc-banner-type-options" role="group" aria-labelledby="cc-banner-type-label">
                  <NcButton v-for="option in bannerTypes" :key="option.value"
                            class="cc-banner-type-card" :data-type="option.value"
                            :pressed="selectedBannerType === option.value"
                            :style="option.value === 'custom'
                              ? {'--cc-banner-type-background':form.banner_color,
                                 '--cc-banner-type-accent':'color-mix(in srgb, '+form.banner_color+' 45%, var(--color-main-text) 55%)'}
                              : undefined"
                            variant="secondary" @click="form.theme = option.value">
                    <span class="cc-banner-type-heading">
                      <NcIconSvgWrapper :path="option.icon" :size="20" />
                      <span>{{ option.label }}</span>
                    </span>
                  </NcButton>
                </div>
                <div class="cc-banner-custom-fields">
                  <div v-if="form.theme === 'custom'" class="cc-banner-color-picker">
                    <span class="cc-native-label">{{ t('communications', 'Banner color') }}</span>
                    <NcColorPicker v-model="form.banner_color" :palette="BANNER_COLOR_PALETTE"
                                   advanced-fields container="body">
                      <NcButton class="cc-banner-color-trigger" variant="secondary"
                                :aria-label="t('communications', 'Choose a custom banner color')">
                        <span class="cc-banner-color-swatch" :style="{backgroundColor: form.banner_color}" aria-hidden="true"></span>
                        <span>{{ form.banner_color }} {{ t('communications', '— Choose color') }}</span>
                      </NcButton>
                    </NcColorPicker>
                    <NcTextField v-model="form.banner_color" :label="t('communications', 'Hexadecimal code (#RRGGBB or #RRGGBBAA)')"
                                 :maxlength="9" />
                    <p class="cc-native-hint">{{ t('communications', 'The color is softened for readability in light and dark themes. Color does not change meaning: use Warning or Error for genuine alerts.') }}</p>
                  </div>
                  <div class="cc-native-control cc-banner-icon-picker">
                    <label for="cc-banner-icon">{{ t('communications', 'Banner icon') }}</label>
                    <NcSelect v-model="form.banner_icon" :options="bannerIconOptions"
                              :reduce="selectable" :clearable="false"
                              label="label" input-id="cc-banner-icon">
                      <template #option="option">
                        <span class="cc-banner-icon-item">
                          <NcIconSvgWrapper v-if="option.path" :path="option.path" :size="20" aria-hidden="true" />
                          <span>{{ option.label }}</span>
                        </span>
                      </template>
                      <template #selected-option="option">
                        <span class="cc-banner-icon-item">
                          <NcIconSvgWrapper v-if="option.path" :path="option.path" :size="18" aria-hidden="true" />
                          <span>{{ option.label }}</span>
                        </span>
                      </template>
                    </NcSelect>
                    <p class="cc-native-hint">{{ t('communications', 'Keep the default icon, choose another, or hide it.') }}</p>
                  </div>
                </div>
              </div>
              <div class="cc-native-appearance-options">
                <NcCheckboxRadioSwitch v-if="form.presentation==='banner'" v-model="form.dismissible" type="switch">{{ t('communications', 'Allow dismissing the banner') }}</NcCheckboxRadioSwitch>
              </div>
              <div v-if="form.presentation==='banner' || form.presentation==='board'" class="cc-native-control">
                <label for="cc-text-align">{{ t('communications', 'Text alignment') }}</label>
                <NcSelect v-model="form.text_align" :options="alignments" :reduce="selectable"
                          :clearable="false" input-id="cc-text-align" />
              </div>
              <div v-if="form.presentation==='banner' || form.presentation==='board'" class="cc-banner-color-picker">
                <label>{{ t('communications', 'Text color (optional)') }}</label>
                <NcColorPicker v-model="selectedTextColor" clearable advanced-fields container="body">
                  <NcButton variant="secondary" :text="t('communications', 'Choose text color')">
                    <template #icon><NcIconSvgWrapper :path="mdiPaletteOutline" :size="18" /></template>
                  </NcButton>
                </NcColorPicker>
                <p class="cc-native-hint">{{ t('communications', 'The chosen color is blended with the theme text color to maintain contrast.') }}</p>
              </div>
            </section>
            </div>

            <div v-else class="cc-wizard-step-content">
              <div class="cc-wizard-step-title">
                <h2>{{ t('communications', 'Review and publish') }}</h2>
                <p>{{ t('communications', 'Review delivery options before activating the publication.') }}</p>
              </div>
            <section class="cc-native-section cc-review-delivery">
              <h2>{{ t('communications', 'Configured delivery') }}</h2>
              <p class="cc-review-delivery-line">{{ deliveryReview }}</p>
              <p v-if="hasAdditionalNotices" class="cc-native-hint">
                {{ t('communications', 'Selected channels share the same message and audience. Email and messaging are queued; banners and modals appear during navigation. Links respect permissions even without an Inbox item.') }}
              </p>
              <NcNoteCard v-if="(noticeEmail || form.gateway_channels.length) && form.status==='active'" type="warning">
                {{ t('communications', 'Activation may send real external messages. Only recipients who opted into each gateway will be included; verify the audience and date range.') }}
              </NcNoteCard>
            </section>
            <section class="cc-native-section">
              <h2>{{ t('communications', 'Publication and frequency') }}</h2>
              <div class="cc-native-pair">
                <div class="cc-native-control cc-format-summary">
                  <span class="cc-native-label">{{ t('communications', 'Visual destination') }}</span>
                  <strong>{{ form.presentation === 'modal' ? t('communications', 'Modal dialog') : form.presentation === 'banner' ? t('communications', 'Contextual banner') : t('communications', 'No contextual display') }}</strong>
                </div>
                <div class="cc-native-control cc-native-priority">
                  <label for="cc-priority">{{ t('communications', 'Priority (0–100)') }}</label>
                  <NcTextField id="cc-priority" v-model="form.priority" :label="t('communications', 'Priority (0–100)')" type="number" label-outside />
                </div>
              </div>
              <div class="cc-native-pair">
                <NcTextField v-model="form.manual_order" :label="t('communications', 'Manual order (lower numbers first)')" type="number" />
              </div>
              <div class="cc-native-pair">
                <div v-if="form.trigger_key !== 'nextcloud.page.opened' && ['banner','modal'].includes(form.presentation) && form.app_target_mode==='legacy'" class="cc-native-control">
                  <label for="cc-placement">{{ t('communications', 'Where to display') }}</label>
                  <NcSelect v-model="form.placement" :options="placementOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-placement" />
                </div>
                <div v-if="['banner','modal'].includes(form.presentation)" class="cc-native-control">
                  <label for="cc-frequency">{{ t('communications', 'Frequency limit') }}</label>
                  <NcSelect v-model="form.frequency" :options="frequencyOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-frequency" />
                </div>
              </div>
              <div v-if="['banner','modal'].includes(form.presentation) && form.trigger_key!=='nextcloud.page.opened'" class="cc-native-control cc-app-targeting">
                <label for="cc-app-target-mode">{{ t('communications', 'Where to display the publication') }}</label>
                <NcSelect v-model="form.app_target_mode" :options="targetModeOptions" :reduce="selectable"
                          :clearable="false" input-id="cc-app-target-mode" />
                <div v-if="form.app_target_mode==='only'||form.app_target_mode==='exclude'" class="cc-native-control">
                  <label for="cc-app-target-apps">{{ t('communications', 'Included or excluded applications *') }}</label>
                  <NcSelect v-model="form.app_target_apps" :options="targetAppOptions" :reduce="selectable"
                            multiple :clearable="true" input-id="cc-app-target-apps"
                            :placeholder="t('communications', 'Select at least one application')" />
                  <p class="cc-native-hint">{{ t('communications', 'Rules are enforced server-side. Unknown pages do not receive publications filtered by application.') }}</p>
                </div>
              </div>
              <div v-if="form.presentation==='banner' && form.trigger_key==='nextcloud.time.window'"
                   class="cc-native-control cc-guest-target">
                <NcCheckboxRadioSwitch v-model="form.guest_visible" type="switch"
                  :disabled="inboxCopy || hasAdditionalNotices || form.audience_type!=='all' || form.category!=='institutional' || form.placement!=='any' || !['legacy','all'].includes(form.app_target_mode)">
                  {{ t('communications', 'Also show to signed-out visitors (public pages)') }}
                </NcCheckboxRadioSwitch>
                <p class="cc-native-hint">{{ t('communications', 'Content will be public, including on the login page and public links. This is only for institution-wide notices scheduled by date without application restrictions. It cannot be combined with Inbox or personal notifications. Disabled by default to protect existing publications.') }}</p>
              </div>
              <div v-if="['banner','modal'].includes(form.presentation)" class="cc-native-pair">
                <NcTextField v-if="form.frequency === 'custom'" v-model="form.min_interval_minutes"
                             :label="t('communications', 'Minimum interval in minutes')" type="number" />
                <NcTextField v-model="form.max_total" :label="t('communications', 'Maximum per user (0 = unlimited)')" type="number" />
                <NcTextField v-model="form.max_per_day" :label="t('communications', 'Maximum in 24 hours (0 = unlimited)')" type="number" />
              </div>
              <div class="cc-native-pair">
                <div class="cc-native-control">
                  <label for="cc-category">{{ t('communications', 'Category') }}</label>
                  <NcSelect v-model="form.category" :options="categoryOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-category" />
                </div>
                <div v-if="form.presentation === 'modal'" class="cc-native-control">
                  <label for="cc-theme">{{ t('communications', 'Visual theme') }}</label>
                  <NcSelect v-model="form.theme" :options="themeOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-theme" />
                </div>
              </div>
              <p class="cc-native-hint">{{ t('communications', 'Sponsored publications are clearly marked and can be hidden by users. No external tracking is used.') }}</p>
              <div v-if="form.trigger_key !== 'nextcloud.time.window'" class="cc-native-pair">
                <NcDateTimePickerNative v-model="form.starts_at" :label="t('communications', 'Start (optional)')" type="datetime-local" clearable />
                <NcDateTimePickerNative v-model="form.ends_at" :label="t('communications', 'End (optional)')" type="datetime-local" clearable />
              </div>
              <div class="cc-native-control">
                <label for="cc-publish">{{ t('communications', 'Publication status') }}</label>
                <NcSelect v-model="form.status" :options="publishOptions" :reduce="selectable"
                          :clearable="false" input-id="cc-publish" />
              </div>
            </section>
              <NcNoteCard type="info" class="cc-wizard-review-note">
                {{ form.status === 'active'
                  ? t('communications', 'The publication will become active according to the configured schedule and rules.')
                  : t('communications', 'While draft or paused, this publication will not be displayed or sent.') }}
              </NcNoteCard>
            </div>

            <div class="cc-native-editor-footer cc-wizard-footer">
              <NcButton :text="t('communications', 'Cancel')" variant="secondary" @click="cancelEdit" />
              <div class="cc-wizard-footer-actions">
                <NcButton v-if="wizardStep > 1" :text="t('communications', 'Back')" @click="goToWizardStep(wizardStep - 1)" />
                <span v-if="wizardStep === 1 && !canContinue" class="cc-wizard-required-hint">{{ t('communications', 'Choose a valid condition to continue.') }}</span>
                <NcButton v-if="wizardStep < 3" variant="primary" :text="nextStepLabel"
                          :disabled="!canContinue" @click="continueWizard" />
                <NcButton v-else variant="primary" :disabled="saving"
                          :text="saving ? t('communications', 'Saving…') : t('communications', 'Save publication')" @click="save" />
              </div>
            </div>
          </div>

          <aside v-if="wizardStep > 1" class="cc-wizard-sidebar" :aria-label="t('communications', 'Publication summary and preview')">
            <section class="cc-wizard-summary">
              <h2><NcIconSvgWrapper :path="mdiBullhornOutline" :size="19" /> {{ t('communications', 'Publication summary') }}</h2>
              <dl class="cc-wizard-summary-list">
                <div>
                  <NcIconSvgWrapper :path="mdiLightningBoltOutline" :size="17" />
                  <dt>{{ t('communications', 'When') }}</dt><dd>{{ summaryWhen }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiAccountGroupOutline" :size="17" />
                  <dt>{{ t('communications', 'For whom') }}</dt><dd>{{ audienceReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiMapMarkerOutline" :size="17" />
                  <dt>{{ t('communications', 'Context') }}</dt><dd>{{ locationReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiBullhornOutline" :size="17" />
                  <dt>{{ t('communications', 'Delivery') }}</dt><dd>{{ deliveryReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiRepeat" :size="17" />
                  <dt>{{ t('communications', 'Frequency') }}</dt><dd>{{ frequencyReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiCalendarRange" :size="17" />
                  <dt>{{ t('communications', 'Availability period') }}</dt><dd>{{ scheduleReview }}</dd>
                </div>
              </dl>
            </section>

            <section class="cc-wizard-preview">
              <h2><NcIconSvgWrapper :path="mdiEyeOutline" :size="19" /> {{ form.presentation==='none'?t('communications', 'Message preview'):t('communications', 'Preview') }}</h2>
              <component :is="form.presentation === 'banner' ? NcNoteCard : 'div'"
                         :type="form.presentation === 'banner' ? bannerPreviewAppearance.type : undefined"
                         :class="form.presentation === 'banner' ? ['cc-wizard-preview-notecard',bannerPreviewAppearance.noteClass] : 'cc-wizard-preview-content'"
                         :style="form.presentation === 'banner' ? bannerPreviewAppearance.style : undefined"
                         :role="form.presentation === 'banner' ? 'group' : undefined"
                         :aria-label="form.presentation === 'banner' ? t('communications', 'Banner visual preview') : undefined"
                         :data-presentation="form.presentation">
                <template v-if="form.presentation === 'banner' && (bannerPreviewAppearance.iconPath || bannerPreviewAppearance.hideIcon)" #icon>
                  <NcIconSvgWrapper v-if="bannerPreviewAppearance.iconPath" class="cc-banner-note-icon" :path="bannerPreviewAppearance.iconPath" :size="20" :style="{color:'var(--note-theme)'}" inline />
                  <span v-else class="cc-note-empty-icon" aria-hidden="true"></span>
                </template>
                <strong :style="form.text_align ? {textAlign:form.text_align} : undefined">{{ form.headline || t('communications', 'Publication title') }}</strong>
                <div class="cc-wizard-preview-message" :style="form.text_align ? {textAlign:form.text_align} : undefined">
                  <NcRichText v-if="form.message" :text="form.message" :use-markdown="true" />
                  <p v-else>{{ t('communications', 'Write a message in the Message and audience step to see a preview.') }}</p>
                </div>
                <span v-if="form.cta_label" class="cc-native-preview-link">{{ form.cta_label }}</span>
              </component>
              <p v-if="form.presentation==='none'" class="cc-native-hint">
                {{ t('communications', 'Content preview. Appearance depends on the channel (email, Notifications, WhatsApp, etc.).') }}
              </p>
              <NcButton wide :text="form.presentation==='none'?t('communications', 'Preview message'):t('communications', 'Test presentation')" :disabled="!form.headline.trim() || !form.message.trim()" @click="openPreview()">
                <template #icon><NcIconSvgWrapper :path="mdiEyeOutline" :size="18" /></template>
              </NcButton>
            </section>
          </aside>
        </div>
      </template>
    </div>
  </div>

  <CampaignPresentation v-if="previewOpen && ['banner','modal'].includes((previewCampaign || form).presentation)" :campaign="previewCampaign || form"
                        :open="previewOpen" preview @close="closePreview" />

  <NcDialog v-if="previewOpen && ['board','none'].includes((previewCampaign || form).presentation)" :open="previewOpen"
            :name="(previewCampaign || form).headline || t('communications', 'Publication preview')"
            container="body" size="normal" @update:open="!$event && closePreview()">
    <div class="cc-board-preview-dialog" role="document" :aria-label="t('communications', 'Publication content')">
      <p v-if="(previewCampaign || form).presentation==='none'" class="cc-native-hint">
        {{ t('communications', 'Content preview; no message will be sent. Each provider may apply its own formatting.') }}
      </p>
      <NcRichText :text="(previewCampaign || form).message || ''" :use-markdown="true" />
    </div>
    <template #actions>
      <NcButton variant="primary" :text="t('communications', 'Close')" @click="closePreview"/>
    </template>
  </NcDialog>

  <NcDialog v-model:open="metricsOpen" :name="'Resultados: ' + (metricsCampaign?.name || '')" size="normal" container="body">
    <div class="cc-native-metrics">
      <p>{{ t('communications', 'Aggregate statistics estimated from browser-reported events. Not audited for billing.') }}</p>
      <p v-if="metricsLoading">{{ t('communications', 'Loading…') }}</p>
      <div v-else-if="metricsData" class="cc-native-metrics-grid">
        <section><strong>{{ metricsData.estimatedImpressions }}</strong><span>{{ t('communications', 'Estimated impressions') }}</span></section>
        <section><strong>{{ metricsData.clicks }}</strong><span>{{ t('communications', 'Clicks') }}</span></section>
        <section><strong>{{ metricsData.dismissals }}</strong><span>{{ t('communications', 'Dismissals') }}</span></section>
        <section><strong>{{ metricsData.queuedOrClaimed }}</strong><span>{{ t('communications', 'Queued or reserved deliveries') }}</span></section>
      </div>
      <NcButton :text="t('communications', 'Close')" @click="metricsOpen = false" />
    </div>
  </NcDialog>

  <NcDialog v-model:open="confirmDeleteOpen" :name="t('communications', 'Permanently delete publication')"
            size="small" container="body">
    <div class="cc-permanent-delete-confirm">
      <p>{{ t('communications', 'Irreversible deletion: the publication, comments, deliveries, metrics, and read records will be removed.') }}</p>
      <p>{{ t('communications', 'To confirm, enter the exact internal name') }} <strong>{{ deleteTarget?.name }}</strong>.</p>
      <NcTextField v-model="deleteName" :label="t('communications', 'Internal name for confirmation')" />
    </div>
    <template #actions>
      <NcButton :text="t('communications', 'Cancel')" @click="confirmDeleteOpen=false" />
      <NcButton variant="error" :text="t('communications', 'Delete permanently')"
                :disabled="deleting || deleteName!==deleteTarget?.name" @click="deletePermanently" />
    </template>
  </NcDialog>

  <NcDialog v-model:open="confirmArchiveOpen" :name="t('communications', 'Archive publication')" size="small" container="body">
    <p>{{ t('communications', 'Archive “') }}{{ archiveTarget?.name }}{{ t('communications', '”? It will no longer be displayed and can be restored later.') }}</p>
    <template #actions>
      <NcButton :text="t('communications', 'Cancel')" @click="confirmArchiveOpen = false" />
      <NcButton variant="error" :text="t('communications', 'Archive')" @click="archive" />
    </template>
  </NcDialog>
</template>
