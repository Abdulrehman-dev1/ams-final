@extends('layouts.master')

@section('content')
<div class="container-fluid">
  {{-- Modern Header --}}
  <div class="modern-card mb-4">
    <div class="modern-card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
            <i class="bi bi-pencil-square me-2" style="color: var(--primary);"></i>
            Edit Employee
          </h2>
          <p class="text-muted mb-0" style="font-size: 0.875rem;">Update employee information</p>
        </div>
        <div>
          <a href="{{ route('acs.people.index') }}" class="btn-modern" style="background: var(--gray-600); color: white;">
            <i class="bi bi-arrow-left"></i>
            <span>Back to List</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Edit Form --}}
  <div class="modern-card">
    <div class="modern-card-header">
      <h5 class="mb-0" style="font-weight: 600;">
        <i class="bi bi-person me-2" style="color: var(--primary);"></i>
        Employee Information
      </h5>
    </div>
    <form action="{{ route('acs.people.update', $employee->id) }}" method="POST" class="modern-card-body">
      @csrf
      @method('PUT')

      {{-- Show Validation Errors --}}
      @if ($errors->any())
        <div class="modern-alert modern-alert-danger mb-4">
          <div>
            <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.5rem;"></i>
          </div>
          <div style="flex: 1;">
            <strong style="font-size: 1rem; display: block; margin-bottom: 0.5rem;">Validation Errors</strong>
            <ul class="mb-0" style="padding-left: 1.5rem;">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      <div class="row">
        {{-- Personal Information --}}
        <div class="col-md-6">
          <h6 class="mb-3" style="font-weight: 600; color: var(--gray-700); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
            Personal Information
          </h6>

          <div class="modern-form-group">
            <label class="modern-form-label">First Name</label>
            <input type="text" 
                   name="first_name" 
                   value="{{ old('first_name', $employee->first_name) }}" 
                   class="modern-form-input" 
                   placeholder="Enter first name">
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Last Name</label>
            <input type="text" 
                   name="last_name" 
                   value="{{ old('last_name', $employee->last_name) }}" 
                   class="modern-form-input" 
                   placeholder="Enter last name">
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Full Name</label>
            <input type="text" 
                   name="full_name" 
                   value="{{ old('full_name', $employee->full_name) }}" 
                   class="modern-form-input" 
                   placeholder="Enter full name">
            <small class="text-muted" style="font-size: 0.75rem;">Leave empty to auto-generate from first and last name</small>
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Person Code</label>
            <input type="text" 
                   name="person_code" 
                   value="{{ old('person_code', $employee->person_code) }}" 
                   class="modern-form-input" 
                   placeholder="Enter person code">
          </div>
        </div>

        {{-- Contact Information --}}
        <div class="col-md-6">
          <h6 class="mb-3" style="font-weight: 600; color: var(--gray-700); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
            Contact Information
          </h6>

          <div class="modern-form-group">
            <label class="modern-form-label">Phone</label>
            <input type="text" 
                   name="phone" 
                   value="{{ old('phone', $employee->phone) }}" 
                   class="modern-form-input" 
                   placeholder="Enter phone number">
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Email</label>
            <input type="email" 
                   name="email" 
                   value="{{ old('email', $employee->email) }}" 
                   class="modern-form-input" 
                   placeholder="Enter email address">
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Group/Department</label>
            <input type="text" 
                   name="group_name" 
                   value="{{ old('group_name', $employee->group_name) }}" 
                   class="modern-form-input" 
                   placeholder="Enter group or department name">
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Description</label>
            <textarea name="description" 
                      class="modern-form-input" 
                      rows="3" 
                      placeholder="Enter description">{{ old('description', $employee->description) }}</textarea>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        {{-- Dates --}}
        <div class="col-md-6">
          <h6 class="mb-3" style="font-weight: 600; color: var(--gray-700); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
            Employment Dates
          </h6>

          <div class="modern-form-group">
            <label class="modern-form-label">Start Date</label>
            <input type="date" 
                   name="start_date" 
                   value="{{ old('start_date', $employee->start_date ? $employee->start_date->timezone($tz)->toDateString() : '') }}" 
                   class="modern-form-input">
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">End Date</label>
            <input type="date" 
                   name="end_date" 
                   value="{{ old('end_date', $employee->end_date ? $employee->end_date->timezone($tz)->toDateString() : '') }}" 
                   class="modern-form-input">
            <small class="text-muted" style="font-size: 0.75rem;">Leave empty if employee is still active</small>
          </div>
        </div>

        {{-- Location & Schedule --}}
        <div class="col-md-6">
          <h6 class="mb-3" style="font-weight: 600; color: var(--gray-700); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
            Location & Schedule
          </h6>

          <div class="modern-form-group">
            <label class="modern-form-label">Base Salary (Monthly)</label>
            <div class="d-flex gap-2">
              <input type="number"
                     name="base_salary"
                     value="{{ old('base_salary', $employee->base_salary) }}"
                     class="modern-form-input"
                     placeholder="e.g. 60000"
                     step="0.01"
                     min="0"
                     style="max-width: 240px;">
              <span class="mini text-muted d-flex align-items-center">Working days used for salary: <strong class="ms-1">26</strong></span>
            </div>
            <small class="text-muted" style="font-size: 0.75rem;">Only admins should modify. Net salary is computed as 26-day cycle in Salary Sheet.</small>
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Latitude</label>
            <input type="number" 
                   name="latitude" 
                   value="{{ old('latitude', $employee->latitude) }}" 
                   class="modern-form-input" 
                   step="0.00000001"
                   min="-90"
                   max="90"
                   placeholder="e.g. 24.8607">
            <small class="text-muted" style="font-size: 0.75rem;">GPS latitude coordinate</small>
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Longitude</label>
            <input type="number" 
                   name="longitude" 
                   value="{{ old('longitude', $employee->longitude) }}" 
                   class="modern-form-input" 
                   step="0.00000001"
                   min="-180"
                   max="180"
                   placeholder="e.g. 67.0011">
            <small class="text-muted" style="font-size: 0.75rem;">GPS longitude coordinate</small>
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Time In (Default: 9:00 AM)</label>
            @php
              $timeInValue = old('time_in', $employee->getAttribute('time_in') ?: '09:00:00');
              if (strlen($timeInValue) === 8) {
                $timeInValue = substr($timeInValue, 0, 5); // Convert H:i:s to H:i
              } elseif (strlen($timeInValue) === 5) {
                // Already in H:i format
              } else {
                $timeInValue = '09:00';
              }
            @endphp
            <input type="time" 
                   name="time_in" 
                   value="{{ $timeInValue }}" 
                   class="modern-form-input">
            <small class="text-muted" style="font-size: 0.75rem;">Expected check-in time. Late after 15 minutes (e.g., 9:15)</small>
          </div>

          <div class="modern-form-group">
            <label class="modern-form-label">Time Out (Default: 7:00 PM)</label>
            @php
              $timeOutValue = old('time_out', $employee->getAttribute('time_out') ?: '19:00:00');
              if (strlen($timeOutValue) === 8) {
                $timeOutValue = substr($timeOutValue, 0, 5); // Convert H:i:s to H:i
              } elseif (strlen($timeOutValue) === 5) {
                // Already in H:i format
              } else {
                $timeOutValue = '19:00';
              }
            @endphp
            <input type="time" 
                   name="time_out" 
                   value="{{ $timeOutValue }}" 
                   class="modern-form-input">
            <small class="text-muted" style="font-size: 0.75rem;">Expected check-out time</small>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        {{-- Status --}}
        <div class="col-md-6">
          <h6 class="mb-3" style="font-weight: 600; color: var(--gray-700); border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">
            Status
          </h6>

          <div class="modern-form-group">
            <label class="modern-form-label">Employee Status</label>
            <div class="d-flex flex-column gap-2 mt-2">
              <label class="d-flex align-items-center gap-2" style="cursor: pointer;">
                <input type="hidden" name="is_enabled" value="0">
                <input type="checkbox" 
                       name="is_enabled" 
                       value="1" 
                       {{ old('is_enabled', $employee->is_enabled ?? true) ? 'checked' : '' }}
                       style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--primary);">
                <span style="font-weight: 500; font-size: 1rem;">
                  <i class="bi bi-check-circle text-success"></i> Enabled
                </span>
              </label>
              <small class="text-muted" style="font-size: 0.875rem; padding-left: 28px;">
                <i class="bi bi-info-circle"></i> Disabled employees won't appear in active employee lists and may be excluded from attendance reports.
              </small>
            </div>
          </div>

          <div class="modern-form-group">
            <div class="modern-alert modern-alert-info">
              <div>
                <i class="bi bi-info-circle" style="font-size: 1.25rem;"></i>
              </div>
              <div style="flex: 1; font-size: 0.875rem;">
                <strong>Note:</strong> Some fields like Person ID and Group ID are managed by the sync process and cannot be edited manually.
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Form Actions --}}
      <div class="modern-card-footer">
        <div class="d-flex justify-content-between align-items-center">
          <a href="{{ route('acs.people.index') }}" class="btn-modern" style="background: var(--gray-600); color: white;">
            <i class="bi bi-x-circle"></i>
            <span>Cancel</span>
          </a>
          <div class="d-flex gap-2">
            <button type="submit" class="btn-modern btn-modern-primary">
              <i class="bi bi-check-circle"></i>
              <span>Update Employee</span>
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection


