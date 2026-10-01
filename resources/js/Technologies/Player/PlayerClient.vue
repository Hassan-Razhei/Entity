<script setup>
import { ref, computed, watch, inject, onMounted } from 'vue';
import DraggableMediaPlayer from './MediaPlayer.vue';
import { router } from '@inertiajs/vue3';
import { useMediaStore } from '@/Technologies/Store/MediaStore';
import { useEditorStore } from '@/Technologies/Store/EditorStore';

const props = defineProps({
    media: Object,
    activeChildId: String, 
    type: String,
    isIntegrated: { type: Boolean, default: false },
    isEmbedded: { type: Boolean, default: false },
    isStudioContext: { type: Boolean, default: false }
});

const emit = defineEmits(['timeupdate', 'segment-change', 'seek', 'toggle-dock', 'navigate', 'navigate-full', 'add-node']);

const mediaStore = useMediaStore();
const isPlayerDocked = inject('isPlayerDocked', { value: false });

onMounted(() => {
    if (props.isEmbedded || props.isIntegrated) {
        mediaStore.setDockMode(false, true);
    }
});

// Sync props to store dynamically (Fix for "Broken" floating logic)
watch(() => props.isIntegrated, (val) => {
    mediaStore.setIntegratedMode(val);
});
watch(() => props.isEmbedded, (val) => {
    if (val) mediaStore.setIntegratedMode(true);
});

const toggleDock = inject('toggleDock', () => {});

// --- Computed State ---

// 1. Get Segments (Tracks/Scenes) from Children
const segments = computed(() => {
    if (props.media?.children?.length) {
        const list = props.media.children.map(child => ({
            id: child.id || child._id,
            slug: child.slug,
            label: child.title, // Map 'title' to 'label' for DraggableMediaPlayer
            title: child.title,
            file_path: child.file_path,
            content: child.content || child.html_content || '', // Pass content for client-side nav
            start: child.start_time || 0,
            end: child.end_time || (child.duration || 0),
            color: child.metadata?.color || '#3b82f6'
        }));
        return list.sort((a, b) => (Number(a.start) || 0) - (Number(b.start) || 0));
    }
    return [];
});

// 2. Determine Current Source
const currentSource = computed(() => {
    // A. Priority: Active Segment (Bundle Mode)
    if (props.activeChildId && segments.value.length) {
        const activeSeg = segments.value.find(s => (s.id || s.slug) === props.activeChildId);
        if (activeSeg?.file_path) {
            // Use streaming route for range request support
            const streamType = props.type === 'audio' ? 'audio' : 'videos';
            const path = `/stream/${streamType}/${activeSeg.file_path}`;
            console.log('[PlayerClient] Using segment source:', path);
            return path;
        }
    }

    // B. Fallback: Main Version File (Single File Mode)
    const mainFile = props.media?.versions?.[0]?.file_path || props.media?.file_path;
    if (mainFile) {
        // Use streaming route for range request support
        const streamType = props.type === 'audio' ? 'audio' : 'videos';
        const path = `/stream/${streamType}/${mainFile}`;
        console.log('[PlayerClient] Using main file source:', path);
        return path;
    }

    // C. Fallback: Sample
    const fallback = props.type === 'audio' 
        ? "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4" 
        : "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4";
    console.log('[PlayerClient] Using fallback source:', fallback);
    return fallback;
});

const currentPoster = computed(() => {
    const poster = props.media?.cover_path 
        ? `/storage/${props.media.cover_path}` 
        : null;
    console.log('[PlayerClient] Poster:', poster);
    return poster;
});

// --- Navigation ---
const handleSegmentChange = (segment) => {
    // When a segment is clicked in the playlist, emit navigate to parent
    const identifier = segment.id || segment._id || segment.slug;
    console.log('[PlayerClient] Segment selected:', segment, 'identifier:', identifier);
    if (identifier) {
        emit('navigate', identifier);
    } else {
        console.warn('[PlayerClient] Segment has no valid identifier:', segment);
    }
};

// --- Store Sync (Metadata) ---
watch(() => props.type, (newType) => {
    mediaStore.type = newType || 'video';
}, { immediate: true });

watch(segments, (newSegments) => {
    mediaStore.segments = newSegments;
}, { immediate: true });

watch(() => props.media, (newMedia) => {
    mediaStore.currentMedia = newMedia;
}, { immediate: true });


// Handle closing the player
// --- Persistence Actions ---
const handleAddSegment = (data) => {
    // Stage 6 Compliance: Emit add-node instead of direct axios
    emit('add-node', {
        type: props.type === 'video' ? 'scene' : 'segment', // Determine default type
        title: data.title,
        time: data.start
    })
};

const handleDeleteSegment = async (segment) => {
    try {
        const id = segment.id || segment.slug;
        await axios.delete(route('api.segments.destroy', id), {
            data: {
                entity_id: props.media.id || props.media._id,
                entity_type: props.type
            }
        });
        
        // Always navigate to full view after deletion to avoid 404 on reload
        router.visit(route('studio.show', { type: props.type, slug: props.media.slug }));
    } catch (error) {
        console.error('[PlayerClient] Error deleting segment:', error);
    }
};

const updateSegment = async (segment) => {
    try {
        const id = segment.id || segment._id || segment.slug;
        const payload = {
            entity_id: props.media.id || props.media._id,
            entity_type: props.type,
            title: segment.title
        };
        
        // Include start_time if it exists
        if (segment.start !== undefined) {
            payload.start_time = segment.start;
        }
        
        console.log('[PlayerClient] Updating segment with payload:', payload);
        const response = await axios.put(route('api.segments.update', id), payload);
        
        console.log('[PlayerClient] Segment updated:', response.data);

        // Update stores immediately for instant UI feedback
        mediaStore.updateSegment({
            id: segment.id,
            slug: segment.slug,
            title: segment.title,
            label: segment.title,
            start: segment.start
        });

        // Sync to EditorStore for UI status bar feedback
        const editorStore = useEditorStore();
        editorStore.lastSaveMessage = response.data.message || 'تم تحديث المقطع بنجاح';
        editorStore.lastSaved = new Date();

        // Wait briefly to ensure MongoDB write is visible to subsequent reads
        // This prevents race conditions where the reload query runs before the write is committed
        await new Promise(resolve => setTimeout(resolve, 500));

        // Force a complete page reload to get fresh aggregated content
        router.visit(window.location.href, { 
            preserveState: false,
            preserveScroll: true 
        });
    } catch (error) {
         console.error('[PlayerClient] Error updating segment:', error);
         alert('حدث خطأ أثناء تحديث المقطع');
    }
};

const closePlayer = () => {
    console.log('[PlayerClient] Closing player. setting isOpen to false');
    mediaStore.setOpen(false);
};

const playerRef = ref(null);
const seek = (time) => playerRef.value?.seek(time);

defineExpose({
    seek
});
</script>

<template>
    <!-- 
        The Draggable Player is mounted here.
        Since it uses 'fixed' positioning, it will float above everything.
        This container should not have visible dimensions that block the UI.
    -->
    <DraggableMediaPlayer
        ref="playerRef"
        :src="currentSource"
        :title="media?.title || 'Unknown Media'"
        :type="type || 'video'"
        :poster="currentPoster"
        :segments="segments"
        :is-docked="isPlayerDocked.value"
        :is-integrated="isIntegrated || isEmbedded"
        :is-studio-context="isStudioContext"
        @close="closePlayer"
        @toggle-dock="() => { toggleDock(); $emit('toggle-dock'); }"
        @segment-change="(seg) => { emit('segment-change', seg); handleSegmentChange(seg); }"
        @timeupdate="(data) => { emit('timeupdate', data); mediaStore.setCurrentTime(data); }"
        @add-segment="handleAddSegment"
        @delete-segment="handleDeleteSegment"
        @update-segment="updateSegment"
        @navigate-full="$emit('navigate-full')"
    />
</template>
