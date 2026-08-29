<footer class="flex flex-col items-center justify-center w-full py-6 text-xs text-gray-400 dark:text-gray-500 gap-2">
    <div class="h-[1px] w-24 bg-gradient-to-r from-transparent via-gray-300 dark:via-gray-700 to-transparent mb-2"></div>
    <div class="flex flex-wrap items-center justify-center gap-2 px-4 text-center">
        <span class="font-semibold text-gray-700 dark:text-gray-300 tracking-wide">WijayaApps</span>
        <span class="px-2 py-0.5 text-[10px] font-medium bg-primary-50 dark:bg-primary-950/30 text-primary-600 dark:text-primary-400 rounded-full border border-primary-100 dark:border-primary-900/40">
            Ver 2.0.0
        </span>
        <span class="text-gray-300 dark:text-gray-700 hidden sm:inline">|</span>
        <span title="Asep Idung" class="cursor-help hover:text-gray-600 dark:hover:text-gray-300 transition-colors">Copyright &copy; {{ date('Y') }}</span>
        <span class="text-gray-300 dark:text-gray-700 hidden sm:inline">|</span>
        {{-- rel="noopener" wajib menyertai target="_blank": tanpa itu halaman
             tujuan bisa menjangkau window aplikasi lewat window.opener. --}}
        <span class="inline-flex items-center gap-1.5">
            {{ __('Made by') }}
            <a
                href="https://www.saepullrock.tech"
                target="_blank"
                rel="noopener noreferrer"
                class="font-bold tracking-[0.2em] text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300 hover:underline underline-offset-4 transition-colors"
            >
                IDNX
            </a>
        </span>
    </div>
</footer>
