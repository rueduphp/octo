{!! Formy::open($item, $crud, $errors) !!}
@php $fields = $item->exists ? $crud['editable'] : $crud['createable']; @endphp

@foreach ($fields as $field => $infos)

@php
    $type = $infos['type'] ?? 'text';
    $options = [];

    if (isset($infos['required'])) {
        $options['required'] = "required";
    }
@endphp

{!! Formy::$type($field, $options) !!}
@endforeach
{!! Formy::submit() !!}
{!! Formy::cancel() !!}
{!! Formy::close() !!}
@section('js')
<script src="https://cdn.ckeditor.com/ckeditor5/10.1.0/classic/ckeditor.js"></script>
<script type="text/javascript">
    ClassicEditor
        .create(document.querySelector('.wysiwyg'), {
            'language'
        })
        .catch( error => {
            console.error( error );
        });

    CKEDITOR.editorConfig = function( config ) {
        config.language = 'fr';
        config.uiColor = '#AADC6E';
    };
</script>
@endsection