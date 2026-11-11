<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
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
  { label: 'Todos os status', value: 'all' },
  { label: 'Ativas', value: 'active' },
  { label: 'Agendadas', value: 'scheduled' },
  { label: 'Encerradas', value: 'ended' },
  { label: 'Rascunhos', value: 'draft' },
  { label: 'Pausadas', value: 'paused' },
  { label: 'Arquivadas', value: 'archived' },
]
const audienceOptions = [
  { label: 'Todos os usuários autenticados', value: 'all' },
  { label: 'Somente administradores', value: 'admins' },
  { label: 'Um ou mais grupos do Nextcloud', value: 'group' },
  { label: 'Usuários específicos', value: 'users' },
]
const publishOptions = [
  { label: 'Rascunho (não publicar nem enviar)', value: 'draft' },
  { label: 'Ativa (publicar e enviar quando elegível)', value: 'active' },
  { label: 'Pausada (não apresentar nem enviar)', value: 'paused' },
]
const placementOptions = computed(() => (catalog.value.automation?.placements || [
  {label:'Qualquer página compatível',value:'any'},
  {label:'Files',value:'files'},
  {label:'Dashboard',value:'dashboard'},
  {label:'Configurações pessoais',value:'settings'},
  {label:'Configurações de administração',value:'admin_settings'},
]).map(p => ({label:p.label,value:p.value})))
const targetModeOptions = [
  {value:'legacy',label:'Uma área definida (modo existente)'},
  {value:'all',label:'Todos os aplicativos compatíveis'},
  {value:'only',label:'Somente os aplicativos selecionados'},
  {value:'exclude',label:'Todos, exceto os aplicativos selecionados'},
]
const targetAppOptions = computed(() => placementOptions.value.filter(p=>p.value!=='any'))
const frequencyOptions = [
  {label:'A cada ocorrência',value:'event'},
  {label:'Uma vez por publicação e usuário',value:'once'},
  {label:'No máximo uma vez a cada 24 horas',value:'daily'},
  {label:'No máximo uma vez a cada 7 dias',value:'weekly'},
  {label:'Intervalo personalizado',value:'custom'},
]
const excludedMatchOptions = [
  {label:'Excluir membros de qualquer grupo (OU)',value:'any'},
  {label:'Excluir somente membros de todos os grupos (E)',value:'all'},
]
const groupMatchOptions = [
  {label:'Qualquer grupo selecionado (OU)',value:'any'},
  {label:'Todos os grupos selecionados (E)',value:'all'},
]
const categoryOptions = [
  {label:'Comunicado institucional',value:'institutional'},
  {label:'Conteúdo patrocinado',value:'sponsored'},
]
const themeOptions = [
  {label:'Padrão do Nextcloud',value:'default'},
  {label:'Destaque institucional',value:'primary'},
  {label:'Informação',value:'info'},
  {label:'Sucesso',value:'success'},
  {label:'Atenção',value:'warning'},
  {label:'Erro',value:'error'},
  {label:'Neutro',value:'neutral'},
  {label:'Personalizado',value:'custom'},
]
// Match the native NcNoteCard variants and icons used by Nextcloud.
const bannerTypes = [
  {value:'info',label:'Informação',icon:mdiInformation},
  {value:'success',label:'Sucesso',icon:mdiCheckboxMarkedCircle},
  {value:'warning',label:'Atenção',icon:mdiAlert},
  {value:'error',label:'Erro',icon:mdiAlertDecagram},
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
  { number:2, label:'Mensagem e público', shortLabel:'Mensagem', icon:mdiMessageTextOutline },
  { number:3, label:'Revisar e publicar', shortLabel:'Publicar', icon:mdiCheckCircleOutline },
]
const nextStepLabel = computed(() => wizardStep.value === 1 ? 'Continuar: Mensagem e público' : 'Continuar: Revisar e publicar')

function checkWizardStep(step) {
  errorMessage.value = ''
  if (step === 1 && !automationReady.value) {
    errorMessage.value = 'Escolha quando a publicação será entregue.'
    return false
  }
  if (step === 1 && form.value.trigger_key === 'nextcloud.time.window' && (!(form.value.starts_at instanceof Date) || Number.isNaN(form.value.starts_at.getTime()))) {
    errorMessage.value = 'Informe a data de início do agendamento.'
    return false
  }
  if (step === 2) {
    if (!form.value.name.trim() || !form.value.headline.trim() || !form.value.message.trim()) {
      errorMessage.value = 'Preencha nome interno, título e mensagem antes de continuar.'
      return false
    }
    if (form.value.audience_type === 'group' && selectedGroups.value.length === 0) {
      errorMessage.value = 'Selecione ao menos um grupo destinatário.'
      return false
    }
    if (form.value.audience_type === 'users' && selectedUsers.value.length === 0) {
      errorMessage.value = 'Selecione ao menos um usuário destinatário.'
      return false
    }
    if (Boolean(form.value.cta_label.trim()) !== Boolean(form.value.cta_url.trim())) {
      errorMessage.value = 'Para exibir um botão, informe o texto e o endereço.'
      return false
    }
    if (!hasDestination.value) {
      errorMessage.value = 'Selecione ao menos uma forma de entrega.'
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
  {id:'notifications',field:'notify_users',label:'Notificações do Nextcloud',
    description:'Aviso no ícone do sino. Precisa do aplicativo Notifications instalado.'},
  {id:'activity',field:'notify_activity',label:'Feed de atividades (Activity)',
    description:'Registra a publicação no feed de atividades, sem abrir janela ou banner.'},
  {id:'email',field:'notify_email',label:'E-mail',
    description:'Envia a mensagem aos destinatários com endereço válido.'},
]
const gatewayDefinitions = [
  {id:'gateway:sms',label:'SMS',description:'Telefone internacional, ex.: +5521999999999.'},
  {id:'gateway:signal',label:'Signal',description:'Número Signal, ex.: +5521999999999.'},
  {id:'gateway:telegram',label:'Telegram',description:'Identificador de chat privado com o bot.'},
  {id:'gateway:gowhatsapp',label:'WhatsApp',description:'Telefone com WhatsApp, em formato internacional.'},
  {id:'gateway:whatsappbusiness',label:'WhatsApp Business',description:'Sujeito às políticas e modelos aprovados do provedor.'},
  {id:'gateway:xmpp',label:'XMPP',description:'Endereço JID do destinatário.'},
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
    const status=form.value.guest_visible ? 'Sem destinatários'
      : selected && !ready ? 'Selecionado · indisponível' : (meta?.status||'Verificando…')
    const help=form.value.guest_visible
      ? 'Banners públicos anônimos não podem enviar mensagens individuais.'
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
    let status=form.value.guest_visible ? 'Sem envio individual'
      : (meta?.status||'Verificando…')
    if (selected && !ready && !form.value.guest_visible) status='Selecionado · indisponível'
    const help=form.value.guest_visible
      ? 'Banners públicos anônimos não enviam mensagens individuais. Desative o modo visitante para configurar destinatários e canais.'
      : (meta?.help || 'A disponibilidade deste canal ainda está sendo verificada.')
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
    description:'Aviso não intrusivo na página, na próxima visita ou ocorrência elegível.',
    selected:form.value.presentation==='banner',selectable:true,
    availability:'ready',status:form.value.presentation==='banner'?'Selecionado':'Disponível',
    help:'O banner aparece na navegação. Selecioná-lo desmarca a janela modal, mas mantém os demais canais.'
  },
  {
    id:'modal',label:'Janela modal',
    description:'Janela em destaque que requer interação, na próxima visita ou ocorrência elegível.',
    selected:form.value.presentation==='modal',selectable:!form.value.guest_visible,
    availability:form.value.guest_visible?'guest':'ready',
    status:form.value.guest_visible?'Incompatível com visitantes':form.value.presentation==='modal'?'Selecionado':'Disponível',
    help:'Modal e banner são alternativas. Escolher um desmarca o outro; e-mail, histórico e notificações permanecem.'
  },
])
const inboxDeliveryOption = computed(() => ({
  id:'inbox',label:'Caixa de entrada',
  description:'Mantém a publicação consultável no Nextcloud, com comentários opcionais.',
  selected:inboxCopy.value,selectable:!form.value.guest_visible,
  availability:form.value.guest_visible?'guest':'ready',
  status:form.value.guest_visible?'Incompatível com visitantes':inboxCopy.value?'Selecionado':'Disponível',
  help:'A Caixa de entrada é um destino independente. Se desativada, mensagens enviadas por outros canais abrem apenas os detalhes autorizados.'
}))

const deliveryReview = computed(() => {
  const surfaces = []
  if (form.value.presentation === 'banner') surfaces.push('Banner contextual')
  if (form.value.presentation === 'modal') surfaces.push('Janela modal')
  if (inboxCopy.value) surfaces.push('Caixa de entrada')

  if (form.value.notify_users) surfaces.push('Notificações do Nextcloud')
  if (form.value.notify_activity) surfaces.push('Feed de atividades')
  if (form.value.notify_email) surfaces.push('E-mail')
  for(const id of (form.value.gateway_channels||[])){
    const gateway=gatewayDefinitions.find(item=>item.id==='gateway:'+id)
    if(gateway) surfaces.push(gateway.label+' (Two-Factor Gateway)')
  }
  return surfaces.join(' · ') || 'Nenhum destino selecionado'
})
// Legacy default/primary become informational banners without migrating data.
const selectedBannerType = computed(() => bannerTypes.some(item => item.value === form.value.theme)
  ? form.value.theme : 'info')
const bannerPreviewAppearance = computed(() => bannerAppearance(form.value))
const bannerIconOptions = BANNER_ICON_OPTIONS.map(({ value, label }) => ({ value, label }))
const languageOptions = [
 {value:'pt_BR',label:'Português (Brasil)'},
 {value:'en_US',label:'Inglês (Estados Unidos)'},
 {value:'es_ES',label:'Espanhol'},
 {value:'fr_FR',label:'Francês'},
 {value:'de_DE',label:'Alemão'},
 {value:'it_IT',label:'Italiano'},
 {value:'ar',label:'Árabe (RTL)'},
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
  {value:'left',label:'À esquerda'}, {value:'center',label:'Centralizado'}, {value:'right',label:'À direita'},
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
const contentFormattingHelp = 'Markdown com formatação e links. HTML e CSS personalizados não são executados.'
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
  if (!response.ok) throw new Error(payload.error || payload.message || 'Falha HTTP ' + response.status)
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
  { label: 'Publicações cadastradas', count: currentCampaigns.value.length },
  { label: 'Ativas agora', count: currentCampaigns.value.filter(c => effectiveStatus(c)==='active').length },
  { label: 'Agendadas', count: currentCampaigns.value.filter(c => effectiveStatus(c)==='scheduled').length },
  { label: 'Rascunhos', count: currentCampaigns.value.filter(c => effectiveStatus(c)==='draft').length },
  { label: 'Pausadas', count: currentCampaigns.value.filter(c => effectiveStatus(c)==='paused').length },
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
  return ts === null ? '' : new Date(ts * 1000).toLocaleDateString('pt-BR')
}
function period(c) {
  if (!c.starts_at && !c.ends_at) return 'Sem prazo'
  if (c.starts_at && c.ends_at) return dateDisplay(c.starts_at) + ' – ' + dateDisplay(c.ends_at)
  return c.starts_at ? 'Desde ' + dateDisplay(c.starts_at) : 'Até ' + dateDisplay(c.ends_at)
}
function groupLabel(c) {
  if (c.audience_type === 'group') {
    const ids = c.audience_groups?.length ? c.audience_groups : (c.audience_group ? [c.audience_group] : [])
    return ids.map(id => groupOptions.value.find(g => g.value === id)?.label || id).join(', ') || 'Nenhum grupo'
  }
  if (c.audience_type === 'users') {
    const users = c.audience_users || []
    return users.length === 1 ? 'Usuário: ' + users[0] : users.length + ' usuários específicos'
  }
  return c.audience_type === 'admins' ? 'Somente administradores' : 'Todos os usuários'
}
function triggerLabel(c) {
  return triggerOptions.value.find(t => t.value === c.trigger_key)?.label || c.trigger_key
}
const summaryAction = computed(() => triggerOptions.value.find(t => t.value === form.value.trigger_key)?.label || 'Selecione uma ação')
const sourceForReview = computed(() => catalog.value.automation?.sources?.find(source =>
  source.actions?.some(action => action.key === form.value.trigger_key)))
const summaryWhen = computed(() => {
  if (!form.value.trigger_key) return 'Não configurado'
  if (form.value.trigger_key === 'nextcloud.page.opened') {
    const place=placementOptions.value.find(p=>p.value===form.value.placement)?.label||form.value.placement
    const filters=Object.entries(form.value.page_filters||{}).map(([name,rule]) =>
      '{'+name+'} '+({equals:'=',prefix:'começa com',digits:'numérico'}[rule.operator]||rule.operator)+' '+(rule.value||'')).join('; ')
    return 'Abrir '+place+(form.value.page_route?' · '+form.value.page_route:' · qualquer página')+(filters?' · '+filters:'')
  }
  return sourceForReview.value ? sourceForReview.value.label + ' → ' + summaryAction.value : summaryAction.value
})
const audienceReview = computed(() => {
  if (form.value.audience_type === 'users') return selectedUsers.value.map(u=>u.displayName||u.id).join(', ') || 'Nenhum usuário selecionado'
  if (form.value.audience_type === 'group') {
    const ids=selectedGroups.value.map(g=>g.displayName||g.id)
    return ids.length ? ids.join(form.value.group_match === 'all' ? ' E ' : ' OU ') : 'Nenhum grupo selecionado'
  }
  return 'Todos os usuários autenticados'
})
const locationReview = computed(()=>form.value.presentation==='none'
  ? (inboxCopy.value?'Caixa de entrada, sem banner ou janela':'Sem exibição na tela')
  : form.value.presentation==='board' ? 'Caixa de entrada'
    : (placementOptions.value.find(x=>x.value===form.value.placement)?.label || form.value.placement))
const frequencyReview = computed(()=>{
  if (eventDrivenDelivery.value) {
    return 'A cada ocorrência, respeitando os limites de frequência e deduplicação'
  }
  if (!['banner','modal'].includes(form.value.presentation)) {
    return 'Canais de saída: uma rodada por ativação'
  }
  const freq=frequencyOptions.find(x=>x.value===form.value.frequency)?.label || form.value.frequency
  const caps=[form.value.max_total>0 ? 'máximo '+form.value.max_total+' por usuário' : '',form.value.max_per_day>0 ? form.value.max_per_day+' em 24 horas' : ''].filter(Boolean)
  return caps.length ? freq+'; '+caps.join('; ') : freq
})
const scheduleReview = computed(()=>{
  const from=form.value.starts_at instanceof Date ? form.value.starts_at.toLocaleDateString('pt-BR') : ''
  const until=form.value.ends_at instanceof Date ? form.value.ends_at.toLocaleDateString('pt-BR') : ''
  return from||until ? 'De '+(from||'agora')+' até '+(until||'sem prazo final') : 'Sem data limite'
})

async function saveEditorGroups() {
  if (editorConfigLoading.value || !catalog.value.canConfigureEditors) return
  editorConfigLoading.value=true
  try {
    const data=await api('/api/editor-groups',{method:'POST',body:{groups:editorGroupValues.value}})
    editorGroupValues.value=data.groups
    flash('Grupos editoriais atualizados.', 'success')
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
    flash(status==='active' ? 'Publicação ativada.' : 'Status atualizado.', 'success')
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
    flash('Ordem de exibição atualizada.','success')
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
    flash('Cópia criada como rascunho.', 'success')
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
    flash('Publicação e histórico de entrega excluídos definitivamente.','success')
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
    flash('Publicação arquivada.', 'success')
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
  if (formDirty.value && !window.confirm('Descartar alterações não salvas?')) return
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
    errorMessage.value = 'Nome interno, título e mensagem são obrigatórios.'
    return
  }
  if (!data.trigger_key) {
    errorMessage.value = 'Escolha quando entregar a publicação.'
    return
  }
  if (data.audience_type === 'group' && !data.audience_groups.length) {
    errorMessage.value = 'Selecione ao menos um grupo.'
    return
  }
  if (data.audience_type === 'users' && !data.audience_users.length) {
    errorMessage.value = 'Selecione ao menos um usuário.'
    return
  }
  if (['only','exclude'].includes(data.app_target_mode)
      && data.presentation!=='board' && data.trigger_key!=='nextcloud.page.opened'
      && !data.app_target_apps.length) {
    errorMessage.value='Selecione ao menos um aplicativo para esta regra de exibição.'
    return
  }
  if (Boolean(data.cta_label.trim()) !== Boolean(data.cta_url.trim())) {
    errorMessage.value = 'Para exibir um botão, informe o texto e o endereço.'
    return
  }
  if (data.trigger_key === 'nextcloud.time.window' && data.starts_at === null) {
    errorMessage.value = 'O agendamento precisa de uma data de início.'
    return
  }
  if (data.starts_at !== null && data.ends_at !== null && data.ends_at <= data.starts_at) {
    errorMessage.value = 'A data de término deve ser posterior ao início.'
    return
  }
  if (!hasDestination.value) {
    errorMessage.value = 'Selecione ao menos uma forma de entrega.'
    return
  }

  if (data.guest_visible && (data.board_visible || data.notify_users
      || data.notify_activity || data.notify_email || data.gateway_channels.length)) {
    errorMessage.value = 'Banners públicos não podem enviar avisos individuais.'
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
        ? 'Publicação salva. Configure a regra no Flow utilizando o nome desta publicação.'
        : 'Publicação salva. Use o identificador apresentado no exemplo de webhook antes de testar.', 'success')
    } else {
      view.value = 'list'
      flash(isNew ? 'Publicação criada.' : 'Publicação atualizada.', 'success')
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
            <h2>Visão geral</h2>
            <p>Acompanhe as publicações em andamento, agendadas e ainda em preparação.</p>
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
            <div><h2>Todas as publicações</h2><p>Gerencie avisos persistentes e contextuais no mesmo lugar. Ordene por arraste ou pelos controles de teclado.</p></div>
            <div class="cc-native-filters">
              <NcTextField v-model="search" label="Buscar publicação" type="search" />
              <NcSelect v-model="filterStatus" :options="statuses" :reduce="selectable"
                        :clearable="false" input-id="cc-status-filter" aria-label="Filtrar por status" />
            </div>
          </div>

          <NcEmptyContent v-if="!loading && !visible.length" name="Nenhuma publicação encontrada"
                          description="Crie uma publicação ou altere os filtros." />
          <div v-else class="cc-native-table-scroll">
            <table class="cc-native-table">
              <thead><tr><th>Publicação</th><th>Gatilho</th><th>Público</th><th>Período</th><th>Status</th><th>Ações</th></tr></thead>
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
                      <NcButton size="small" text="Editar" @click="openEdit(campaign)" />
                      <NcActions :aria-label="'Ações de ' + campaign.name" :open="rowMenuOpen[campaign.id] || false"
                                 @update:open="rowMenuOpen[campaign.id] = $event">
                        <NcActionButton v-if="campaign.status!=='archived'" :disabled="orderSaving"
                                        @click="moveCampaign(campaign,'up')">
                          <template #icon><NcIconSvgWrapper :path="mdiArrowUp" :size="20" /></template>
                          Mover acima
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status!=='archived'" :disabled="orderSaving"
                                        @click="moveCampaign(campaign,'down')">
                          <template #icon><NcIconSvgWrapper :path="mdiArrowDown" :size="20" /></template>
                          Mover abaixo
                        </NcActionButton>
                        <NcActionButton @click="openPreview(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiEyeOutline" :size="20" /></template>
                          Visualizar apresentação
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status !== 'archived'"
                                        @click="updateStatus(campaign, campaign.status === 'active' ? 'paused' : 'active')">
                          <template #icon><NcIconSvgWrapper :path="campaign.status === 'active' ? mdiPauseCircleOutline : mdiPlayCircleOutline" :size="20" /></template>
                          {{ campaign.status === 'active' ? 'Pausar' : 'Ativar' }}
                        </NcActionButton>
                        <NcActionButton v-else @click="updateStatus(campaign, 'draft')">
                          <template #icon><NcIconSvgWrapper :path="mdiBackupRestore" :size="20" /></template>
                          Restaurar
                        </NcActionButton>
                        <NcActionButton @click="openMetrics(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiChartBar" :size="20" /></template>
                          Estatísticas
                        </NcActionButton>
                        <NcActionButton @click="duplicate(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiContentCopy" :size="20" /></template>
                          Duplicar
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status === 'archived'" @click="askDelete(campaign)">
                          Excluir definitivamente
                        </NcActionButton>
                        <NcActionButton v-if="campaign.status !== 'archived'" @click="askArchive(campaign)">
                          <template #icon><NcIconSvgWrapper :path="mdiArchiveOutline" :size="20" /></template>
                          Arquivar
                        </NcActionButton>
                      </NcActions>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <nav v-if="pageCount > 1" class="cc-native-pagination" aria-label="Páginas de publicações">
            <span>Exibindo {{ (currentPage - 1) * PAGE_SIZE + 1 }}–{{ Math.min(currentPage * PAGE_SIZE, visible.length) }} de {{ visible.length }}</span>
            <NcButton text="Anterior" :disabled="currentPage <= 1" @click="page = currentPage - 1" />
            <span>Página {{ currentPage }} de {{ pageCount }}</span>
            <NcButton text="Próxima" :disabled="currentPage >= pageCount" @click="page = currentPage + 1" />
          </nav>
        </section>
        <section class="cc-editor-settings" aria-label="Configurações editoriais">
        <details v-if="catalog.canConfigureEditors" class="cc-progressive-details cc-editor-delegation">
          <summary>Permissões editoriais</summary>
          <p class="cc-native-hint">Os grupos autorizados podem gerenciar publicações. Somente administradores globais podem alterar as permissões.</p>
          <div class="cc-native-control">
            <label for="cc-editor-groups">Grupos com permissão editorial</label>
            <NcSelect v-model="editorGroupValues" :options="groupOptions" :reduce="selectable"
                      multiple :clearable="true" input-id="cc-editor-groups"
                      placeholder="Selecionar grupos de editores" />
          </div>
          <NcButton text="Salvar delegação" variant="primary" :disabled="editorConfigLoading" @click="saveEditorGroups" />
        </details>

        </section>
        <p class="cc-native-caption">As impressões informadas pelo navegador são estimativas, não métricas auditadas de leitura ou faturamento.</p>
      </template>

      <template v-else>
        <div class="cc-native-heading cc-native-heading-editor">
          <div class="cc-wizard-heading">
            <NcButton variant="tertiary" text="Voltar às publicações" @click="cancelEdit" />
            <h1>{{ editMode ? 'Editar publicação' : 'Nova publicação' }}</h1>
            <p>Uma mensagem, várias formas de entrega. Configure o momento, o conteúdo e os destinatários.</p>
          </div>
        </div>

        <nav class="cc-wizard-steps" aria-label="Etapas da publicação">
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
              <p class="cc-native-hint">Defina quando a publicação fica disponível. A forma de exibição e os canais de aviso serão escolhidos na próxima etapa, em um único lugar.</p>
              <AutomationBuilder v-model="form.trigger_key" v-model:placement="form.placement"
                                 v-model:page-route="form.page_route" v-model:page-filters="form.page_filters"
                                 :catalog="catalog.automation" :campaign-id="editorId" @intent-change="onIntentChange" />
              <section v-if="form.trigger_key === 'nextcloud.time.window'" class="cc-native-section cc-schedule-inline">
                <h3>Vigência</h3>
                <div class="cc-native-pair">
                  <NcDateTimePickerNative v-model="form.starts_at" label="Início *" type="datetime-local" clearable />
                  <NcDateTimePickerNative v-model="form.ends_at" label="Término (opcional)" type="datetime-local" clearable />
                </div>
              </section>
            </div>

            <div v-else-if="wizardStep === 2" class="cc-wizard-step-content">
              <div class="cc-wizard-step-title">
                <h2>Mensagem, público e entrega</h2>
                <p>Escreva uma mensagem, escolha os destinatários e configure todos os canais no mesmo fluxo.</p>
              </div>
            <section class="cc-native-section">
              <h2>Identificação</h2>
              <NcTextField v-model="form.name" label="Nome interno *" :maxlength="160"
                           placeholder="Ex.: Aviso de boas-vindas" />
              <NcTextArea v-model="form.description" label="Descrição para sua equipe" :maxlength="700" />
            </section>
            <section class="cc-native-section">
              <h2>Conteúdo</h2>
              <NcTextField v-model="form.headline" label="Título exibido *" :maxlength="180" />
              <div class="cc-native-rich">
                <MarkdownEditor v-model="form.message" label="Mensagem exibida *"
                                :description="contentFormattingHelp" placeholder="Escreva a mensagem…"
                                min-height="130px" max-height="410px" />
              </div>
              <details class="cc-progressive-details" :open="Boolean(form.cta_label || form.cta_url) || undefined">
                <summary>Adicionar botão de ação (opcional)</summary>
                <div class="cc-native-pair">
                  <NcTextField v-model="form.cta_label" label="Texto do botão" :maxlength="80" />
                  <NcTextField v-model="form.cta_url" label="Link HTTP(S), mailto:, tel: ou caminho interno" :maxlength="1900" />
                </div>
              </details>
              <details class="cc-progressive-details cc-translation-options">
                <summary>Traduções da mensagem (opcional)</summary>
                <p class="cc-native-hint">Escreva uma versão para o idioma do usuário. Campos vazios usam o texto original.</p>
                <div class="cc-native-control">
                  <label for="cc-translation-locale">Idioma da tradução</label>
                  <NcSelect v-model="translationLocale" :options="languageOptions"
                            :reduce="selectable" :clearable="false"
                            input-id="cc-translation-locale"/>
                </div>
                <NcTextField v-model="translatedHeadline" label="Título traduzido" :maxlength="180"/>
                <NcTextArea v-model="translatedMessage" label="Mensagem traduzida" :maxlength="4000"/>
                <NcTextField v-model="translatedCta" label="Botão traduzido" :maxlength="80"/>
              </details>
            </section>
            <section class="cc-native-section">
              <h2>Destinatários</h2>
              <div class="cc-native-control">
                <label for="cc-audience">Enviar para</label>
                <NcSelect v-model="form.audience_type" :options="audienceOptions" :reduce="selectable"
                          :clearable="false" input-id="cc-audience" />
              </div>
              <div v-if="form.audience_type === 'group'" class="cc-native-control">
                <NcSelectUsers v-model="selectedGroups" :options="groupChoices"
                               input-id="cc-group" input-label="Grupos destinatários"
                               placeholder="Selecione um ou mais grupos" multiple keep-open />
                <div class="cc-native-control">
                  <label for="cc-group-match">Combinação dos grupos</label>
                  <NcSelect v-model="form.group_match" :options="groupMatchOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-group-match" />
                </div>
              </div>
              <div v-if="form.audience_type === 'users'" class="cc-native-control">
                <NcSelectUsers v-model="selectedUsers" :options="userChoices"
                               input-id="cc-users" input-label="Usuários destinatários"
                               placeholder="Pesquise pelo nome ou identificador"
                               multiple keep-open @search="searchUsers" />
                <p class="cc-native-hint">Pesquise pelo menos dois caracteres. Somente os usuários selecionados receberão a publicação.</p>
              </div>
              <details class="cc-progressive-details" :open="selectedExcludedGroups.length > 0 || undefined">
                <summary>Excluir grupos de destinatários (opcional)</summary>
                <div class="cc-native-control">
                  <NcSelectUsers v-model="selectedExcludedGroups" :options="groupChoices"
                                 input-id="cc-excluded" input-label="Grupos excluídos"
                                 placeholder="Esses grupos não receberão a publicação"
                                 multiple keep-open />
                  <label for="cc-excluded-match">Como combinar os grupos excluídos</label>
                  <NcSelect v-model="form.excluded_match" :options="excludedMatchOptions"
                            :reduce="selectable" :clearable="false" input-id="cc-excluded-match"/>
                  <p class="cc-native-hint">Qualquer grupo (OU) exclui quem está em pelo menos um grupo.
                    Todos os grupos (E) exclui somente quem pertence a todos os grupos ao mesmo tempo.
                    A exclusão prevalece sobre a inclusão.</p>
                </div>
              </details>
            </section>

            <section class="cc-native-section cc-delivery-section">
              <h2>Formas de entrega</h2>
              <p class="cc-native-hint">Selecione um ou mais destinos para esta comunicação. Banner contextual e janela modal são alternativas: escolher um desmarca o outro. Caixa de entrada, notificações, atividades, e-mail e mensageiros podem ser combinados livremente.</p>
              <div class="cc-delivery-selector" role="group" aria-label="Formas de entrega da publicação">
                <DeliveryChannelOption v-for="channel in visualDeliveryOptions" :key="channel.id"
                                       :channel="channel" @change="onDeliveryChannelChange"/>
                <DeliveryChannelOption :channel="inboxDeliveryOption" @change="onDeliveryChannelChange"/>
                <DeliveryChannelOption v-for="channel in deliveryChannelOptions" :key="channel.id"
                                       :channel="channel" @change="onDeliveryChannelChange"/>
                <details class="cc-gateway-provider-details">
                  <summary>
                    <strong>Mensageiros e SMS (Two-Factor Gateway)</strong>
                    <span>{{ gatewayGroup?.installed
                      ? gatewayReadyCount+' de '+gatewayOptions.length+' provedores configurados'
                      : 'Aplicativo não instalado' }}</span>
                  </summary>
                  <p class="cc-native-hint">
                    WhatsApp, SMS, Signal, Telegram e XMPP são destinos independentes.
                    Cada destinatário precisa autorizar seu canal e informar o identificador
                    em suas preferências de avisos. Conteúdo patrocinado exige consentimento adicional.
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
                Permitir comentários na Caixa de entrada
              </NcCheckboxRadioSwitch>
              <NcNoteCard v-if="!hasDestination" type="warning" class="cc-delivery-empty-warning">
                Selecione ao menos uma forma de entrega para continuar.
              </NcNoteCard>
              <p v-if="eventDrivenDelivery" class="cc-native-hint">
                Cada ocorrência do evento aciona somente o destinatário autorizado.
                Os canais de saída são processados pela fila; banner ou modal aparece
                na próxima visita elegível. A frequência selecionada limita todas as
                entregas e não há disparo em massa na ativação.
              </p>
              <p v-else-if="['banner','modal'].includes(form.presentation)" class="cc-native-hint">
                Banner ou modal aparece na próxima visita elegível; os outros canais escolhidos
                são processados na fila uma vez por ativação, respeitando a data de início.
              </p>
              <p v-if="noticeEmail" class="cc-native-hint">
                E-mail exige servidor configurado e endereço válido. O envio não pode ser desfeito.
              </p>
              <p v-if="editorId!==null && form.status==='active' && hasAdditionalNotices"
                 class="cc-native-hint">
                Alterar os canais não reenvia mensagens anteriores. Novas ocorrências seguem a
                configuração ativa, respeitando consentimento, vigência e frequência.
              </p>
              <div v-if="form.presentation === 'banner'" class="cc-banner-type-settings">
                <div class="cc-banner-type-label">
                  <span id="cc-banner-type-label" class="cc-native-label">Tipo de mensagem</span>
                  <p class="cc-native-hint">Use um tipo nativo ou escolha uma aparência neutra ou personalizada.</p>
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
                    <span class="cc-native-label">Cor do banner</span>
                    <NcColorPicker v-model="form.banner_color" :palette="BANNER_COLOR_PALETTE"
                                   advanced-fields container="body">
                      <NcButton class="cc-banner-color-trigger" variant="secondary"
                                aria-label="Escolher cor personalizada do banner">
                        <span class="cc-banner-color-swatch" :style="{backgroundColor: form.banner_color}" aria-hidden="true"></span>
                        <span>{{ form.banner_color }} — Escolher cor</span>
                      </NcButton>
                    </NcColorPicker>
                    <NcTextField v-model="form.banner_color" label="Código hexadecimal (#RRGGBB ou #RRGGBBAA)"
                                 :maxlength="9" />
                    <p class="cc-native-hint">A cor será suavizada para preservar a leitura nos temas claro e escuro. A cor não muda a semântica da mensagem: use Atenção ou Erro para alertas reais.</p>
                  </div>
                  <div class="cc-native-control cc-banner-icon-picker">
                    <label for="cc-banner-icon">Ícone do banner</label>
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
                    <p class="cc-native-hint">Você pode manter o ícone padrão, escolher outro ou ocultá-lo.</p>
                  </div>
                </div>
              </div>
              <div class="cc-native-appearance-options">
                <NcCheckboxRadioSwitch v-if="form.presentation==='banner'" v-model="form.dismissible" type="switch">Permitir dispensar o banner</NcCheckboxRadioSwitch>
              </div>
              <div v-if="form.presentation==='banner' || form.presentation==='board'" class="cc-native-control">
                <label for="cc-text-align">Alinhamento do texto</label>
                <NcSelect v-model="form.text_align" :options="alignments" :reduce="selectable"
                          :clearable="false" input-id="cc-text-align" />
              </div>
              <div v-if="form.presentation==='banner' || form.presentation==='board'" class="cc-banner-color-picker">
                <label>Cor do texto (opcional)</label>
                <NcColorPicker v-model="selectedTextColor" clearable advanced-fields container="body">
                  <NcButton variant="secondary" text="Escolher cor de texto">
                    <template #icon><NcIconSvgWrapper :path="mdiPaletteOutline" :size="18" /></template>
                  </NcButton>
                </NcColorPicker>
                <p class="cc-native-hint">A cor escolhida será mesclada com o texto do tema para manter contraste.</p>
              </div>
            </section>
            </div>

            <div v-else class="cc-wizard-step-content">
              <div class="cc-wizard-step-title">
                <h2>Revisar e publicar</h2>
                <p>Confira como a publicação será entregue antes de ativá-la.</p>
              </div>
            <section class="cc-native-section cc-review-delivery">
              <h2>Entrega configurada</h2>
              <p class="cc-review-delivery-line">{{ deliveryReview }}</p>
              <p v-if="hasAdditionalNotices" class="cc-native-hint">
                Os canais selecionados compartilham a mensagem e o público. Entregas por e-mail e mensageiros são processadas pela fila; banner e modal aparecem na navegação. Os links respeitam as permissões mesmo sem Caixa de entrada.
              </p>
              <NcNoteCard v-if="(noticeEmail || form.gateway_channels.length) && form.status==='active'" type="warning">
                Ao ativar, o sistema poderá enviar mensagens externas reais. Somente destinatários
                que autorizaram cada gateway individualmente serão incluídos; confira o público e o período.
              </NcNoteCard>
            </section>
            <section class="cc-native-section">
              <h2>Publicação e frequência</h2>
              <div class="cc-native-pair">
                <div class="cc-native-control cc-format-summary">
                  <span class="cc-native-label">Destino visual</span>
                  <strong>{{ form.presentation === 'modal' ? 'Janela modal' : form.presentation === 'banner' ? 'Banner contextual' : 'Sem exibição contextual' }}</strong>
                </div>
                <div class="cc-native-control cc-native-priority">
                  <label for="cc-priority">Prioridade (0–100)</label>
                  <NcTextField id="cc-priority" v-model="form.priority" label="Prioridade (0–100)" type="number" label-outside />
                </div>
              </div>
              <div class="cc-native-pair">
                <NcTextField v-model="form.manual_order" label="Ordem manual (menor aparece primeiro)" type="number" />
              </div>
              <div class="cc-native-pair">
                <div v-if="form.trigger_key !== 'nextcloud.page.opened' && ['banner','modal'].includes(form.presentation) && form.app_target_mode==='legacy'" class="cc-native-control">
                  <label for="cc-placement">Onde apresentar</label>
                  <NcSelect v-model="form.placement" :options="placementOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-placement" />
                </div>
                <div v-if="['banner','modal'].includes(form.presentation)" class="cc-native-control">
                  <label for="cc-frequency">Limite de frequência</label>
                  <NcSelect v-model="form.frequency" :options="frequencyOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-frequency" />
                </div>
              </div>
              <div v-if="['banner','modal'].includes(form.presentation) && form.trigger_key!=='nextcloud.page.opened'" class="cc-native-control cc-app-targeting">
                <label for="cc-app-target-mode">Onde apresentar a publicação</label>
                <NcSelect v-model="form.app_target_mode" :options="targetModeOptions" :reduce="selectable"
                          :clearable="false" input-id="cc-app-target-mode" />
                <div v-if="form.app_target_mode==='only'||form.app_target_mode==='exclude'" class="cc-native-control">
                  <label for="cc-app-target-apps">Aplicativos permitidos ou excluídos *</label>
                  <NcSelect v-model="form.app_target_apps" :options="targetAppOptions" :reduce="selectable"
                            multiple :clearable="true" input-id="cc-app-target-apps"
                            placeholder="Selecione ao menos um aplicativo" />
                  <p class="cc-native-hint">As regras são verificadas pelo servidor. Uma página desconhecida não recebe publicações com filtros de aplicativo.</p>
                </div>
              </div>
              <div v-if="form.presentation==='banner' && form.trigger_key==='nextcloud.time.window'"
                   class="cc-native-control cc-guest-target">
                <NcCheckboxRadioSwitch v-model="form.guest_visible" type="switch"
                  :disabled="inboxCopy || hasAdditionalNotices || form.audience_type!=='all' || form.category!=='institutional' || form.placement!=='any' || !['legacy','all'].includes(form.app_target_mode)">
                  Exibir também a visitantes sem login (páginas públicas)
                </NcCheckboxRadioSwitch>
                <p class="cc-native-hint">O conteúdo será público inclusive na página de login e em links públicos.
                  Somente avisos institucionais para todos, programados por data e sem restrições de aplicativo.
                  Não pode ser combinado com Caixa de entrada ou avisos individuais.
                  Desativado por padrão para proteger publicações existentes.</p>
              </div>
              <div v-if="['banner','modal'].includes(form.presentation)" class="cc-native-pair">
                <NcTextField v-if="form.frequency === 'custom'" v-model="form.min_interval_minutes"
                             label="Intervalo mínimo em minutos" type="number" />
                <NcTextField v-model="form.max_total" label="Máximo total por usuário (0 = ilimitado)" type="number" />
                <NcTextField v-model="form.max_per_day" label="Máximo em 24 horas (0 = ilimitado)" type="number" />
              </div>
              <div class="cc-native-pair">
                <div class="cc-native-control">
                  <label for="cc-category">Categoria</label>
                  <NcSelect v-model="form.category" :options="categoryOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-category" />
                </div>
                <div v-if="form.presentation === 'modal'" class="cc-native-control">
                  <label for="cc-theme">Tema visual</label>
                  <NcSelect v-model="form.theme" :options="themeOptions" :reduce="selectable"
                            :clearable="false" input-id="cc-theme" />
                </div>
              </div>
              <p class="cc-native-hint">Publicações patrocinadas são identificadas como tal e permitem ocultação pelo usuário. Não há rastreamento externo.</p>
              <div v-if="form.trigger_key !== 'nextcloud.time.window'" class="cc-native-pair">
                <NcDateTimePickerNative v-model="form.starts_at" label="Início (opcional)" type="datetime-local" clearable />
                <NcDateTimePickerNative v-model="form.ends_at" label="Término (opcional)" type="datetime-local" clearable />
              </div>
              <div class="cc-native-control">
                <label for="cc-publish">Situação da publicação</label>
                <NcSelect v-model="form.status" :options="publishOptions" :reduce="selectable"
                          :clearable="false" input-id="cc-publish" />
              </div>
            </section>
              <NcNoteCard type="info" class="cc-wizard-review-note">
                {{ form.status === 'active'
                  ? 'A publicação ficará ativa conforme a vigência e as regras definidas.'
                  : 'Enquanto estiver em rascunho ou pausada, esta publicação não será apresentada nem enviada.' }}
              </NcNoteCard>
            </div>

            <div class="cc-native-editor-footer cc-wizard-footer">
              <NcButton text="Cancelar" variant="secondary" @click="cancelEdit" />
              <div class="cc-wizard-footer-actions">
                <NcButton v-if="wizardStep > 1" text="Voltar" @click="goToWizardStep(wizardStep - 1)" />
                <span v-if="wizardStep === 1 && !canContinue" class="cc-wizard-required-hint">Defina uma condição válida para continuar.</span>
                <NcButton v-if="wizardStep < 3" variant="primary" :text="nextStepLabel"
                          :disabled="!canContinue" @click="continueWizard" />
                <NcButton v-else variant="primary" :disabled="saving"
                          :text="saving ? 'Salvando…' : 'Salvar publicação'" @click="save" />
              </div>
            </div>
          </div>

          <aside v-if="wizardStep > 1" class="cc-wizard-sidebar" aria-label="Resumo e prévia da publicação">
            <section class="cc-wizard-summary">
              <h2><NcIconSvgWrapper :path="mdiBullhornOutline" :size="19" /> Resumo da publicação</h2>
              <dl class="cc-wizard-summary-list">
                <div>
                  <NcIconSvgWrapper :path="mdiLightningBoltOutline" :size="17" />
                  <dt>Quando</dt><dd>{{ summaryWhen }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiAccountGroupOutline" :size="17" />
                  <dt>Para quem</dt><dd>{{ audienceReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiMapMarkerOutline" :size="17" />
                  <dt>Contexto</dt><dd>{{ locationReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiBullhornOutline" :size="17" />
                  <dt>Entrega</dt><dd>{{ deliveryReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiRepeat" :size="17" />
                  <dt>Frequência</dt><dd>{{ frequencyReview }}</dd>
                </div>
                <div>
                  <NcIconSvgWrapper :path="mdiCalendarRange" :size="17" />
                  <dt>Vigência</dt><dd>{{ scheduleReview }}</dd>
                </div>
              </dl>
            </section>

            <section class="cc-wizard-preview">
              <h2><NcIconSvgWrapper :path="mdiEyeOutline" :size="19" /> {{ form.presentation==='none'?'Prévia da mensagem':'Prévia' }}</h2>
              <component :is="form.presentation === 'banner' ? NcNoteCard : 'div'"
                         :type="form.presentation === 'banner' ? bannerPreviewAppearance.type : undefined"
                         :class="form.presentation === 'banner' ? ['cc-wizard-preview-notecard',bannerPreviewAppearance.noteClass] : 'cc-wizard-preview-content'"
                         :style="form.presentation === 'banner' ? bannerPreviewAppearance.style : undefined"
                         :role="form.presentation === 'banner' ? 'group' : undefined"
                         :aria-label="form.presentation === 'banner' ? 'Prévia visual do banner' : undefined"
                         :data-presentation="form.presentation">
                <template v-if="form.presentation === 'banner' && (bannerPreviewAppearance.iconPath || bannerPreviewAppearance.hideIcon)" #icon>
                  <NcIconSvgWrapper v-if="bannerPreviewAppearance.iconPath" class="cc-banner-note-icon" :path="bannerPreviewAppearance.iconPath" :size="20" :style="{color:'var(--note-theme)'}" inline />
                  <span v-else class="cc-note-empty-icon" aria-hidden="true"></span>
                </template>
                <strong :style="form.text_align ? {textAlign:form.text_align} : undefined">{{ form.headline || 'Título da publicação' }}</strong>
                <div class="cc-wizard-preview-message" :style="form.text_align ? {textAlign:form.text_align} : undefined">
                  <NcRichText v-if="form.message" :text="form.message" :use-markdown="true" />
                  <p v-else>Escreva a mensagem na etapa Mensagem e público para visualizar a prévia.</p>
                </div>
                <span v-if="form.cta_label" class="cc-native-preview-link">{{ form.cta_label }}</span>
              </component>
              <p v-if="form.presentation==='none'" class="cc-native-hint">
                Prévia do conteúdo. A aparência final depende de cada canal (e-mail, Notifications, WhatsApp etc.).
              </p>
              <NcButton wide :text="form.presentation==='none'?'Pré-visualizar mensagem':'Testar apresentação'" :disabled="!form.headline.trim() || !form.message.trim()" @click="openPreview()">
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
            :name="(previewCampaign || form).headline || 'Prévia da publicação'"
            container="body" size="normal" @update:open="!$event && closePreview()">
    <div class="cc-board-preview-dialog" role="document" aria-label="Conteúdo da publicação">
      <p v-if="(previewCampaign || form).presentation==='none'" class="cc-native-hint">
        Prévia do conteúdo; nenhum envio será realizado. Cada provedor pode aplicar sua própria formatação.
      </p>
      <NcRichText :text="(previewCampaign || form).message || ''" :use-markdown="true" />
    </div>
    <template #actions>
      <NcButton variant="primary" text="Fechar" @click="closePreview"/>
    </template>
  </NcDialog>

  <NcDialog v-model:open="metricsOpen" :name="'Resultados: ' + (metricsCampaign?.name || '')" size="normal" container="body">
    <div class="cc-native-metrics">
      <p>Estatísticas agregadas e estimadas a partir dos eventos reportados pelo navegador. Não são auditadas para faturamento.</p>
      <p v-if="metricsLoading">Carregando…</p>
      <div v-else-if="metricsData" class="cc-native-metrics-grid">
        <section><strong>{{ metricsData.estimatedImpressions }}</strong><span>Impressões estimadas</span></section>
        <section><strong>{{ metricsData.clicks }}</strong><span>Cliques</span></section>
        <section><strong>{{ metricsData.dismissals }}</strong><span>Dispensas</span></section>
        <section><strong>{{ metricsData.queuedOrClaimed }}</strong><span>Entregas na fila ou reservadas</span></section>
      </div>
      <NcButton text="Fechar" @click="metricsOpen = false" />
    </div>
  </NcDialog>

  <NcDialog v-model:open="confirmDeleteOpen" name="Excluir publicação definitivamente"
            size="small" container="body">
    <div class="cc-permanent-delete-confirm">
      <p>Exclusão irreversível: a publicação e seus comentários, recebimentos, métricas e registros de leitura serão removidos.</p>
      <p>Para confirmar, digite exatamente o nome interno <strong>{{ deleteTarget?.name }}</strong>.</p>
      <NcTextField v-model="deleteName" label="Nome interno para confirmação" />
    </div>
    <template #actions>
      <NcButton text="Cancelar" @click="confirmDeleteOpen=false" />
      <NcButton variant="error" text="Excluir definitivamente"
                :disabled="deleting || deleteName!==deleteTarget?.name" @click="deletePermanently" />
    </template>
  </NcDialog>

  <NcDialog v-model:open="confirmArchiveOpen" name="Arquivar publicação" size="small" container="body">
    <p>Arquivar “{{ archiveTarget?.name }}”? Ela deixará de ser apresentada e poderá ser restaurada depois.</p>
    <template #actions>
      <NcButton text="Cancelar" @click="confirmArchiveOpen = false" />
      <NcButton variant="error" text="Arquivar" @click="archive" />
    </template>
  </NcDialog>
</template>
