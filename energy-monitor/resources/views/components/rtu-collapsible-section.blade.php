@props([
    'sectionKey' => '',
    'title' => '',
    'icon' => 'heroicon-o-square-3-stack-3d',
    'isCollapsed' => false,
    'displayOrder' => 0,
])

<div class="rtu-collapsible-section bg-white rounded-lg shadow-sm border border-gray-200 mb-4" 
     data-section="{{ $sectionKey }}" 
     data-display-order="{{ $displayOrder }}">
    
    <!-- Section Header -->
    <div class="section-header flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 transition-colors duration-200"
         onclick="toggleSection('{{ $sectionKey }}')">
        
        <div class="flex items-center space-x-3">
            <!-- Section Icon -->
            <div class="section-icon flex-shrink-0">
                @svg($icon, 'w-5 h-5 text-gray-600')
            </div>
            
            <!-- Section Title -->
            <h3 class="section-title text-lg font-semibold text-gray-900">
                {{ $title }}
            </h3>
        </div>
        
        <!-- Collapse/Expand Icon -->
        <div class="collapse-icon transition-transform duration-300 {{ $isCollapsed ? 'rotate-180' : '' }}">
            @svg('heroicon-o-chevron-up', 'w-5 h-5 text-gray-500')
        </div>
    </div>
    
    <!-- Section Content -->
    <div class="section-content {{ $isCollapsed ? 'collapsed' : 'expanded' }}" 
         style="{{ $isCollapsed ? 'max-height: 0; overflow: hidden;' : '' }}">
        <div class="section-body p-4 pt-0">
            {{ $slot }}
        </div>
    </div>
</div>

<style>
.rtu-collapsible-section .section-content {
    transition: max-height 0.3s ease-in-out, opacity 0.2s ease-in-out;
    overflow: hidden;
}

.rtu-collapsible-section .section-content.collapsed {
    max-height: 0 !important;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}

.rtu-collapsible-section .section-content.expanded {
    max-height: 1000px;
    opacity: 1;
}

.rtu-collapsible-section .section-header:hover .section-icon svg {
    color: #3b82f6;
}

.rtu-collapsible-section .section-header:hover .section-title {
    color: #1f2937;
}

.rtu-collapsible-section .collapse-icon {
    transition: transform 0.3s ease-in-out;
}

/* Loading state */
.rtu-collapsible-section.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Animation for smooth expand/collapse */
@keyframes expandSection {
    from {
        max-height: 0;
        opacity: 0;
    }
    to {
        max-height: 1000px;
        opacity: 1;
    }
}

@keyframes collapseSection {
    from {
        max-height: 1000px;
        opacity: 1;
    }
    to {
        max-height: 0;
        opacity: 0;
    }
}

.rtu-collapsible-section .section-content.expanding {
    animation: expandSection 0.3s ease-in-out forwards;
}

.rtu-collapsible-section .section-content.collapsing {
    animation: collapseSection 0.3s ease-in-out forwards;
}
</style>