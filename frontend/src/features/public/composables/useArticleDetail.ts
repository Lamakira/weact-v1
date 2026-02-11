import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  fetchPublicArticleDetail,
  type PublicArticleDetail,
} from '../services/publicArticlesApi'

/**
 * Composable for loading a single public article by slug
 */
export function useArticleDetail() {
  const route = useRoute()

  const article = ref<PublicArticleDetail | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  async function loadArticle(slug: string): Promise<void> {
    isLoading.value = true
    error.value = null
    notFound.value = false

    const result = await fetchPublicArticleDetail(slug)

    if (result.success && result.article) {
      article.value = result.article
    } else {
      error.value = result.error ?? 'Une erreur est survenue'
      notFound.value = result.notFound ?? false
    }

    isLoading.value = false
  }

  onMounted(() => {
    const slug = route.params.slug as string
    if (slug) {
      loadArticle(slug)
    }
  })

  return {
    article,
    isLoading,
    error,
    notFound,
    loadArticle,
  }
}
