package com.fyndable.mobile.ui.theme

import android.os.Build
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext

private val FyndableDarkScheme = darkColorScheme(
    primary = FyndableBlue,
    onPrimary = Color(0xFF003547),
    primaryContainer = Color(0xFF004D6B),
    onPrimaryContainer = Color(0xFFB3E5FF),
    secondary = FyndablePurple,
    onSecondary = Color(0xFFFFFFFF),
    secondaryContainer = Color(0xFF3A3757),
    onSecondaryContainer = Color(0xFFDED9FF),
    tertiary = FyndableMagenta,
    onTertiary = Color(0xFFFFFFFF),
    tertiaryContainer = Color(0xFF54224E),
    onTertiaryContainer = Color(0xFFFFD8EE),
    background = DarkBackground,
    onBackground = DarkOnSurface,
    surface = DarkSurface,
    onSurface = DarkOnSurface,
    surfaceVariant = DarkSurfaceVariant,
    onSurfaceVariant = DarkOnSurfaceVariant,
    outline = Color(0xFF64748B),
    outlineVariant = Color(0xFF334155),
    error = DangerRed,
    onError = Color(0xFFFFFFFF),
    errorContainer = Color(0xFF93000A),
    onErrorContainer = Color(0xFFFFDAD6),
)

private val FyndableLightScheme = lightColorScheme(
    primary = FyndableBlue,
    onPrimary = Color(0xFFFFFFFF),
    primaryContainer = Color(0xFFE1F5FF),
    onPrimaryContainer = Color(0xFF001E2E),
    secondary = FyndablePurple,
    onSecondary = Color(0xFFFFFFFF),
    secondaryContainer = Color(0xFFE0DEFF),
    onSecondaryContainer = Color(0xFF1B1A35),
    tertiary = FyndableMagenta,
    onTertiary = Color(0xFFFFFFFF),
    tertiaryContainer = Color(0xFFFFD8EE),
    onTertiaryContainer = Color(0xFF38072F),
    background = LightBackground,
    onBackground = LightOnSurface,
    surface = LightSurface,
    onSurface = LightOnSurface,
    surfaceVariant = LightSurfaceVariant,
    onSurfaceVariant = LightOnSurfaceVariant,
    outline = Color(0xFF64748B),
    outlineVariant = Color(0xFFCBD5E1),
    error = DangerRed,
    onError = Color(0xFFFFFFFF),
    errorContainer = Color(0xFFFFDAD6),
    onErrorContainer = Color(0xFF410E0B),
)

@Composable
fun FyndableTheme(
    darkTheme: Boolean = true, // Always dark theme for Fyndable branding
    dynamicColor: Boolean = false, // Disabled to keep Fyndable brand colors
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalContext.current
            if (darkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        }
        darkTheme -> FyndableDarkScheme
        else -> FyndableLightScheme
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = FyndableTypography,
        content = content
    )
}
