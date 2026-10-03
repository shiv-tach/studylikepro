<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-primary text-white rounded-xl font-bold text-sm shadow-md shadow-primary/20 hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/25 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all duration-200']) }}>
    {{ $slot }}
</button>
