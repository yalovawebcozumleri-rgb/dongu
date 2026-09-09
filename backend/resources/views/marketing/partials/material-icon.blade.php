@if ($type === 'pet')
    <svg viewBox="0 0 24 32"><g fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6v4.5l2.1 3.1c.6.9.9 1.9.9 3V27a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V12.6c0-1.1.3-2.1.9-3L9 6.5V2Z"/><line x1="9" y1="5" x2="15" y2="5"/><rect x="8" y="15" width="8" height="7" rx="1.2" stroke-opacity=".58"/></g></svg>
@elseif ($type === 'glass')
    <svg viewBox="0 0 24 32"><g fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6v8.1c0 .9.3 1.7 1 2.3l1 1c.7.7 1 1.5 1 2.5V27a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V15.9c0-1 .3-1.8 1-2.5l1-1c.7-.6 1-1.4 1-2.3V2Z"/><line x1="9" y1="6" x2="15" y2="6"/><line x1="9" y1="16" x2="9" y2="25" stroke-opacity=".5"/></g></svg>
@else
    <svg viewBox="0 0 24 32"><g fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5.5v21c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5v-21"/><ellipse cx="12" cy="5.5" rx="5" ry="2.5"/><path d="M10.1 5.3c.8-.7 2.7-.7 3.8 0l-1.3 1.2h-2.5V5.3Z"/><path d="M7 25.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5"/><line x1="9" y1="11" x2="9" y2="22" stroke-opacity=".45"/></g></svg>
@endif
