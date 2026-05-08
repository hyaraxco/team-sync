export const normalizePermissions = (permissions = []) =>
    permissions.map((permission) => (typeof permission === "string" ? permission : permission?.name)).filter(Boolean);

export const hasRoutePermissionAccess = (permissions = [], meta = {}) => {
    const normalizedPermissions = normalizePermissions(permissions);
    const requiredPermission = meta.requiredPermission;
    const requiredAnyPermissions = meta.requiredAnyPermissions;
    const requiresAuth = meta.requiresAuth === true;
    const allowAuthenticated = meta.allowAuthenticated === true;
    const hasExplicitPermissionGuard =
        Boolean(requiredPermission) || (Array.isArray(requiredAnyPermissions) && requiredAnyPermissions.length > 0);

    // Fail closed for authenticated pages unless the route explicitly opts out.
    if (requiresAuth && !hasExplicitPermissionGuard && !allowAuthenticated) {
        return false;
    }

    if (requiredPermission && !normalizedPermissions.includes(requiredPermission)) {
        return false;
    }

    if (
        Array.isArray(requiredAnyPermissions) &&
        requiredAnyPermissions.length > 0 &&
        !requiredAnyPermissions.some((permission) => normalizedPermissions.includes(permission))
    ) {
        return false;
    }

    return true;
};
