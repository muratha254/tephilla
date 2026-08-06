@php
    $documents = [
        [
            'label' => 'License Document',
            'path' => $driver->license_doc,
            'meta' => $driver->license_number ? 'License No: ' . $driver->license_number : null,
        ],
        [
            'label' => 'ID Document',
            'path' => $driver->id_doc,
            'meta' => $driver->id_number ? 'ID No: ' . $driver->id_number : null,
        ],
    ];
@endphp

<div class="fleet-panel fleet-driver-documents-card">
    <div class="fleet-panel-header">License &amp; Documents</div>
    <div class="fleet-panel-body">
        <div class="fleet-driver-documents-grid">
            @foreach ($documents as $document)
                @php
                    $url = $driver->storedFileUrl($document['path']);
                    $isImage = $driver->storedFileIsImage($document['path']);
                @endphp
                <div class="fleet-driver-document-card">
                    <div class="fleet-driver-document-head">
                        <strong>{{ $document['label'] }}</strong>
                        @if ($document['meta'])
                            <span>{{ $document['meta'] }}</span>
                        @endif
                    </div>

                    @if ($url)
                        <div class="fleet-driver-document-preview {{ $isImage ? 'is-image' : 'is-file' }}">
                            @if ($isImage)
                                <a href="{{ $url }}" target="_blank" rel="noopener">
                                    <img src="{{ $url }}" alt="{{ $document['label'] }}">
                                </a>
                            @else
                                <div class="fleet-driver-document-file">
                                    <i class="fa fa-file-pdf-o"></i>
                                    <span>{{ $driver->storedFileLabel($document['path']) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="fleet-driver-document-actions">
                            <a href="{{ $url }}" class="fleet-btn fleet-btn-outline" target="_blank" rel="noopener">
                                <i class="fa fa-eye"></i> View
                            </a>
                            <a href="{{ $url }}" class="fleet-btn fleet-btn-outline" download>
                                <i class="fa fa-download"></i> Download
                            </a>
                        </div>
                    @else
                        <div class="fleet-driver-document-empty">
                            <i class="fa fa-file-o"></i>
                            <span>No document uploaded</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
