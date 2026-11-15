<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="root" class="markdown-heading-menu">
		<!-- TRANSLATORS Aria label for the markdown editor toolbar button that opens the menu for choosing paragraph or heading styles. -->
		<button ref="toggle"
			type="button"
			class="markdown-heading-menu__toggle"
			:aria-expanded="isOpen ? 'true' : 'false'"
			aria-haspopup="menu"
			:aria-label="t('communications', 'Heading style')"
			@click.stop="toggleMenu"
			@keydown.escape.prevent="closeMenu">
			<NcIconSvgWrapper :path="isOpen ? mdiChevronUp : mdiChevronDown" :size="18" />
			<span>H</span>
		</button>

		<ul v-if="isOpen"
			ref="menu"
			class="markdown-heading-menu__menu"
			role="menu"
			:style="menuStyle"
			@keydown.escape.prevent="closeMenu">
			<li role="none">
				<button type="button"
					role="menuitem"
					class="markdown-heading-menu__item"
					@click="clearHeading">
					<span class="markdown-heading-menu__option markdown-heading-menu__option--p">
						<!-- TRANSLATORS Menu option in the markdown editor that removes heading formatting and returns the text to normal paragraph style. -->
						{{ t('communications', 'Paragraph') }}
					</span>
				</button>
			</li>
			<li v-for="level in headingLevels" :key="level" role="none">
				<button type="button"
					role="menuitem"
					class="markdown-heading-menu__item"
					@click="applyHeading(level)">
					<span :class="headingClass(level)">
						<span class="markdown-heading-menu__prefix">H{{ level }} </span>
						<span>{{ headingText(level) }}</span>
					</span>
				</button>
			</li>
		</ul>
	</div>
</template>

<script setup lang="ts">
import { mdiChevronDown, mdiChevronUp } from '@mdi/js'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { CSSProperties } from 'vue'

import { t } from '@nextcloud/l10n'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineOptions({
	name: 'HeadingMenu',
})

type HeadingLevel = 1 | 2 | 3 | 4 | 5 | 6
const emit = defineEmits<{ 'clear-heading': []; 'apply-heading': [level: HeadingLevel] }>()

const headingLevels: HeadingLevel[] = [1, 2, 3, 4, 5, 6]

const root = ref<HTMLElement | null>(null)
const toggle = ref<HTMLButtonElement | null>(null)
const menu = ref<HTMLUListElement | null>(null)
const isOpen = ref(false)
const menuStyle = ref<CSSProperties>({})

const headingClass = (level: number): string[] => {
	return ['markdown-heading-menu__option', `markdown-heading-menu__option--${level}`]
}

const headingText = (level: number): string => {
	// TRANSLATORS Label shown in the markdown editor heading menu for each option. {level} is the heading level number.
	return t('communications', 'Heading {level}', { level })
}

const clearHeading = () => {
	emit('clear-heading')
	closeMenu()
}

const applyHeading = (level: HeadingLevel): void => {
	emit('apply-heading', level)
	closeMenu()
}

const closeMenu = () => {
	isOpen.value = false
	menuStyle.value = {}
}

const toggleMenu = async () => {
	isOpen.value = !isOpen.value
	if (isOpen.value) {
		await nextTick()
		updateMenuPosition()
	}
}

const updateMenuPosition = () => {
	if (!toggle.value || !menu.value) {
		return
	}

	const toggleRect = toggle.value.getBoundingClientRect()
	const menuRect = menu.value.getBoundingClientRect()
	const viewportWidth = window.innerWidth
	const top = toggleRect.bottom + 6
	const left = Math.min(Math.max(8, toggleRect.left), Math.max(8, viewportWidth - menuRect.width - 8))

	menuStyle.value = {
		position: 'fixed',
		top: `${top}px`,
		left: `${left}px`,
		visibility: 'visible',
	}
}

const onDocumentPointerDown = (event: PointerEvent): void => {
	if (!isOpen.value || !root.value) {
		return
	}
	if (event.target instanceof Node && !root.value.contains(event.target)) {
		closeMenu()
	}
}

onMounted(() => {
	document.addEventListener('pointerdown', onDocumentPointerDown)
	window.addEventListener('scroll', updateMenuPosition, true)
	window.addEventListener('resize', updateMenuPosition)
})

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', onDocumentPointerDown)
	window.removeEventListener('scroll', updateMenuPosition, true)
	window.removeEventListener('resize', updateMenuPosition)
})
</script>

<style scoped lang="scss">
.markdown-heading-menu {
	position: relative;
	display: inline-flex;
	flex: 0 0 auto;

	&__toggle {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		height: 32px;
		padding: 0 10px;
		border: 1px solid transparent;
		border-radius: var(--border-radius-element, 10px);
		background: var(--color-background-hover);
		color: var(--color-main-text);
		font-weight: 700;
		cursor: pointer;
	}

	&__toggle:hover,
	&__toggle[aria-expanded='true'] {
		background: var(--color-primary-light);
	}

	&__menu {
		position: fixed;
		top: 0;
		inset-inline-start: 0;
		visibility: hidden;
		z-index: 1200;
		margin: 0;
		padding: 6px 0;
		list-style: none;
		min-width: 170px;
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: 10px;
		box-shadow: 0 10px 26px var(--color-box-shadow);
	}

	&__item {
		display: flex;
		align-items: center;
		width: 100%;
		padding: 6px 12px;
		border: 0;
		background: transparent;
		color: var(--color-main-text);
		text-align: start;
		cursor: pointer;
	}

	&__item:hover {
		background: var(--color-background-hover);
	}

	&__option {
		display: inline-flex;
		align-items: baseline;
		gap: 8px;
		line-height: 1.2;
		width: 100%;
	}

	&__prefix {
		opacity: 0.72;
		font-size: 0.78em;
		font-weight: 700;
		letter-spacing: 0.03em;
	}

	&__option--p {
		font-size: 1rem;
		font-weight: 500;
		line-height: 1.35;
	}

	&__option--1 {
		font-size: 1.5rem;
		font-weight: 800;
		line-height: 1.1;
		letter-spacing: -0.01em;
	}

	&__option--2 {
		font-size: 1.3rem;
		font-weight: 750;
		line-height: 1.15;
		letter-spacing: -0.01em;
	}

	&__option--3 {
		font-size: 1.15rem;
		font-weight: 700;
		line-height: 1.2;
	}

	&__option--4 {
		font-size: 1.03rem;
		font-weight: 650;
		line-height: 1.25;
	}

	&__option--5 {
		font-size: 0.94rem;
		font-weight: 620;
		line-height: 1.3;
	}

	&__option--6 {
		font-size: 0.86rem;
		font-weight: 600;
		line-height: 1.35;
		letter-spacing: 0.01em;
	}
}
</style>
