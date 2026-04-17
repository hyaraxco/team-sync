export function handleError(error) {
    if (!error.response) {
        return 'Network error. Please check your connection.'
    }

    const status = error.response.status

    if (status === 422) {
        return error.response.data.errors
    } else if (status === 401) {
        return error.response.data.message
    } else if (status === 400) {
        return error.response.data.message
    } else if (status === 403) {
        return error.response.data.message || 'You do not have permission to perform this action.'
    } else if (status === 404) {
        return error.response.data.message || 'Resource not found.'
    } else if (status === 429) {
        return 'Too many requests. Please try again later.'
    } else if (status === 500) {
        return error.response.data.message
    } else {
        return error.response.data?.message || 'An unexpected error occurred.'
    }
}
