@php
    $groupClass = $groupClass ?? 'permission-group';
    $checkAllId = $checkAllId ?? $groupClass;
    $sectionTitle = $sectionTitle ?? __('Assign Permission to Roles');
    $selectedPermissionIds = $selectedPermissionIds ?? [];
    $itemLabel = $itemLabel ?? __('Section');
    $rows = collect($rows ?? [])->filter(function ($row) use ($permissions) {
        foreach ($row['actions'] as $action) {
            if (in_array($action['ability'] . ' ' . $row['permission'], (array) $permissions, true)) {
                return true;
            }
        }

        return false;
    })->values();
@endphp

<div class="col-md-12">
    <div class="form-group">
        @if ($rows->isNotEmpty())
            <h6 class="my-3">{{ $sectionTitle }}</h6>
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" class="form-check-input custom_align_middle" id="{{ $checkAllId }}">
                        </th>
                        <th>{{ $itemLabel }}</th>
                        <th>{{ __('Permissions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $rowKey = preg_replace('/[^A-Za-z0-9]/', '', $row['permission']);
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input ischeck {{ $groupClass }}"
                                    data-id="{{ $rowKey }}">
                            </td>
                            <td>
                                <label class="ischeck {{ $groupClass }}" data-id="{{ $rowKey }}">
                                    {{ __($row['label']) }}
                                </label>
                            </td>
                            <td>
                                <div class="row">
                                    @foreach ($row['actions'] as $action)
                                        @php
                                            $permissionName = $action['ability'] . ' ' . $row['permission'];
                                            $permissionId = array_search($permissionName, (array) $permissions, true);
                                        @endphp
                                        @if ($permissionId !== false)
                                            <div class="col-md-3 custom-control custom-checkbox">
                                                {{ Form::checkbox('permissions[]', $permissionId, in_array($permissionId, $selectedPermissionIds, true), ['class' => 'form-check-input isscheck ' . $groupClass . ' isscheck_' . $rowKey, 'id' => 'permission' . $permissionId]) }}
                                                {{ Form::label('permission' . $permissionId, $action['label'], ['class' => 'custom-control-label']) }}
                                                <br>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted mb-0">{{ __('No permissions available in this section.') }}</p>
        @endif
    </div>
</div>
