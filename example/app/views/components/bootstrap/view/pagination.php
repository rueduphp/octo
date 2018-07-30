<nav aria-label="Page navigation">
    @php
        $paginator = $paginator->toArray();
        $arrows = true;
    @endphp

    @if ($paginator['current_page'] !== $paginator['last_page'])
    <ul class="pagination {{ $class ?? '' }}">
        @if ($paginator['current_page'] > 1)
        @istrue($arrows)
            <li class="page-item">
                <a class="page-link" href="{{ $paginator['prev_page_url'] }}" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                    <span class="sr-only">@lng('crud.general.pagination_prev')</span>
                </a>
            </li>
        @endistrue
        @endif

        @foreach($elements[0] as $page => $link)
            <li class="page-item @if($paginator['current_page'] === $page) active @endif">
                <a class="page-link" href="{{ $link }}">{!! $page !!}</a>
            </li>
        @endforeach

        @if ($paginator['current_page'] !== $paginator['last_page'])
        @istrue($arrows)
            <li class="page-item">
                <a class="page-link" href="{{ $paginator['next_page_url'] }}" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                    <span class="sr-only">@lng('crud.general.pagination_next')</span>
                </a>
            </li>
        @endistrue
        @endif
    </ul>
    @endif
</nav>