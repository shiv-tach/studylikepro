<script setup>
import { computed } from 'vue';
import AppIcon from '../landing/components/AppIcon.vue';
import SiteFooter from '../landing/components/SiteFooter.vue';
import SiteHeader from '../landing/components/SiteHeader.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
const support = computed(() => props.config.support ?? {});
const old = computed(() => props.config.old ?? {});
const errors = computed(() => props.config.errors ?? {});

const fieldClass = 'mt-1 block w-full rounded-xl border border-line bg-white px-3 py-2.5 text-sm text-ink shadow-xs transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
const labelClass = 'text-xs font-semibold tracking-widest text-ink-400 uppercase';
const cardClass = 'hairline rounded-2xl bg-white p-6';
</script>

<template>
    <div class="lp-theme flex min-h-screen flex-col bg-white text-ink">
        <a
            href="#top"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-brand-500 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
        >
            Skip to content
        </a>

        <SiteHeader
            :app-name="config.appName"
            :authenticated="config.authenticated"
            :urls="urls"
            :logo="config.images.logo"
            :home-url="urls.home"
            active-section="contact"
        />

        <main class="flex-1">
            <section id="top" class="relative overflow-hidden border-b border-line bg-white">
                <div class="lp-aurora lp-aurora-soft" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="lp-grid-lines pointer-events-none absolute inset-0" aria-hidden="true"></div>

                <div class="relative mx-auto w-full max-w-6xl px-5 pb-14 pt-12 lg:pb-20 lg:pt-20">
                    <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500" aria-label="Breadcrumb">
                        <a :href="urls.home" class="transition hover:text-ink">Home</a>
                        <span aria-hidden="true">/</span>
                        <span class="font-medium text-ink-700">{{ copy.navContact }}</span>
                    </nav>

                    <p class="mt-8 text-sm font-medium text-brand-600">{{ copy.eyebrow }}</p>
                    <h1 class="mt-3 max-w-3xl text-display-hero font-semibold">{{ copy.title }}</h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">{{ copy.intro }}</p>
                </div>
            </section>

            <section class="bg-ink-50 py-14 lg:py-16">
                <div class="mx-auto grid w-full max-w-6xl gap-6 px-5 lg:grid-cols-3">
                    <div class="lg:col-span-2" style="box-shadow: var(--shadow-sm)">
                        <div :class="cardClass">
                            <div
                                v-if="config.sent"
                                class="mb-6 flex items-start gap-3 rounded-2xl border border-go/30 bg-go/10 p-4 text-sm text-go-ink"
                                role="status"
                            >
                                <AppIcon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{{ copy.sent }}</span>
                            </div>

                            <p v-if="errors.website" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert">
                                {{ errors.website }}
                            </p>

                            <form method="POST" :action="urls.contactSend" class="space-y-4">
                                <input type="hidden" name="_token" :value="config.csrfToken" />

                                <!-- Honeypot: hidden from people, irresistible to bots. -->
                                <div class="hidden" aria-hidden="true">
                                    <label for="website">Website</label>
                                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="name" :class="labelClass">{{ copy.name }}</label>
                                        <input
                                            id="name"
                                            name="name"
                                            type="text"
                                            required
                                            :value="old.name"
                                            :class="fieldClass"
                                            :aria-invalid="errors.name ? 'true' : undefined"
                                        />
                                        <p v-if="errors.name" class="mt-1 text-xs text-rose-600">{{ errors.name }}</p>
                                    </div>
                                    <div>
                                        <label for="email" :class="labelClass">{{ copy.email }}</label>
                                        <input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            :value="old.email"
                                            :class="fieldClass"
                                            :aria-invalid="errors.email ? 'true' : undefined"
                                        />
                                        <p v-if="errors.email" class="mt-1 text-xs text-rose-600">{{ errors.email }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label for="subject" :class="labelClass">{{ copy.subject }}</label>
                                    <input
                                        id="subject"
                                        name="subject"
                                        type="text"
                                        required
                                        :value="old.subject"
                                        :placeholder="copy.subjectPlaceholder"
                                        :class="fieldClass"
                                        :aria-invalid="errors.subject ? 'true' : undefined"
                                    />
                                    <p v-if="errors.subject" class="mt-1 text-xs text-rose-600">{{ errors.subject }}</p>
                                </div>

                                <div>
                                    <label for="message" :class="labelClass">{{ copy.message }}</label>
                                    <textarea
                                        id="message"
                                        name="message"
                                        rows="7"
                                        required
                                        :placeholder="copy.messagePlaceholder"
                                        :class="fieldClass"
                                        :aria-invalid="errors.message ? 'true' : undefined"
                                    >{{ old.message }}</textarea>
                                    <p v-if="errors.message" class="mt-1 text-xs text-rose-600">{{ errors.message }}</p>
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-full bg-brand-500 px-7 py-3 text-sm font-medium text-white transition hover:bg-brand-600"
                                    style="box-shadow: var(--shadow-brand)"
                                >
                                    {{ copy.send }}
                                    <AppIcon name="arrow-right" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <div :class="cardClass">
                            <h2 class="font-display text-lg font-semibold text-ink">{{ copy.otherWays }}</h2>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div v-if="support.email">
                                    <dt :class="labelClass">{{ copy.emailLabel }}</dt>
                                    <dd class="mt-1">
                                        <a :href="`mailto:${support.email}`" class="font-medium text-brand-600 underline underline-offset-4">{{ support.email }}</a>
                                    </dd>
                                </div>
                                <div v-if="support.phone">
                                    <dt :class="labelClass">{{ copy.phoneLabel }}</dt>
                                    <dd class="mt-1 text-ink-700">
                                        <a :href="`tel:${support.phone}`" class="transition hover:text-ink">{{ support.phone }}</a>
                                    </dd>
                                </div>
                                <div v-if="support.hours">
                                    <dt :class="labelClass">{{ copy.hoursLabel }}</dt>
                                    <dd class="mt-1 text-ink-700">{{ support.hours }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div :class="cardClass">
                            <h2 class="font-display text-lg font-semibold text-ink">{{ copy.fasterTitle }}</h2>
                            <ul class="mt-3 space-y-2.5 text-sm leading-relaxed text-ink-700">
                                <li v-for="tip in copy.fasterTips" :key="tip" class="flex gap-2">
                                    <AppIcon name="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-go-ink" />
                                    <span>{{ tip }}</span>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </section>
        </main>

        <SiteFooter
            :app-name="config.appName"
            :urls="urls"
            :support="support"
            :logo="config.images.logo"
            :landing-url="urls.home"
        />
    </div>
</template>
