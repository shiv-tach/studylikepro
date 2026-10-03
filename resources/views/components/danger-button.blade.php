<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-rose-600 text-white rounded-xl font-bold text-sm shadow-md shadow-rose-600/20 hover:bg-rose-500 hover:shadow-lg hover:shadow-rose-600/25 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-rose-500/30 transition-all duration-200']) }}>
    {{ $slot }}
</button>
