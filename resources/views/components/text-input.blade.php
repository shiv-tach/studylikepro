@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'mt-1.5 block w-full px-4 py-2.5 text-sm border border-slate-200 bg-slate-50 text-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary dark:border-slate-800/80 dark:bg-slate-950 dark:text-slate-200 dark:focus:ring-primary/30 transition-all duration-200']) }}>
