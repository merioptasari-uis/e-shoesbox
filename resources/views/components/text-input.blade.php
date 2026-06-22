@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700 focus:border-violet-500 dark:focus:border-violet-600 focus:ring-violet-500 dark:focus:ring-violet-600 rounded-md shadow-sm']) }}>
