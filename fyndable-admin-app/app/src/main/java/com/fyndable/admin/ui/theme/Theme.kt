package com.fyndable.admin.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.core.view.WindowCompat

// Fyndable brand colors (from SaaS dashboard white-label defaults)
val FyndablePrimary = Color(0xFF379FD3)
val FyndableSecondary = Color(0xFF8F39AC)
val FyndablePrimaryDark = Color(0xFF1E7BA8)
val FyndableSecondaryDark = Color(0xFF6E2A86)

private val LightColors = lightColorScheme(
    primary = FyndablePrimary,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFD0EAF5),
    onPrimaryContainer = Color(0xFF003547),
    secondary = FyndableSecondary,
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFEFD9F5),
    onSecondaryContainer = Color(0xFF2D0A3A),
    background = Color(0xFFF7F9FC),
    onBackground = Color(0xFF1A1C1E),
    surface = Color.White,
    onSurface = Color(0xFF1A1C1E),
    surfaceVariant = Color(0xFFE3E7EC),
    onSurfaceVariant = Color(0xFF44474E),
    error = Color(0xFFBA1A1A),
    onError = Color.White,
)

private val DarkColors = darkColorScheme(
    primary = FyndablePrimaryDark,
    onPrimary = Color.White,
    primaryContainer = Color(0xFF004D6E),
    onPrimaryContainer = Color(0xFFC5E7FF),
    secondary = FyndableSecondaryDark,
    onSecondary = Color.White,
    secondaryContainer = Color(0xFF4A1A5E),
    onSecondaryContainer = Color(0xFFEFD9F5),
    background = Color(0xFF111316),
    onBackground = Color(0xFFE3E7EC),
    surface = Color(0xFF1A1C1E),
    onSurface = Color(0xFFE3E7EC),
    surfaceVariant = Color(0xFF44474E),
    onSurfaceVariant = Color(0xFFC3C7CF),
    error = Color(0xFFFFB4AB),
    onError = Color(0xFF690005),
)

@Composable
fun FyndableAdminTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    val colorScheme = if (darkTheme) DarkColors else LightColors

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = Color.Transparent.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = FyndableTypography,
        content = content,
    )
}

// Compact typography using the default system font (Outfit can be bundled later).
val FyndableTypography = androidx.compose.material3.Typography(
    headlineMedium = TextStyle(fontWeight = FontWeight.Bold, fontSize = 28.sp, lineHeight = 36.sp),
    headlineSmall = TextStyle(fontWeight = FontWeight.Bold, fontSize = 22.sp, lineHeight = 28.sp),
    titleLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 20.sp, lineHeight = 26.sp),
    titleMedium = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 16.sp, lineHeight = 22.sp),
    bodyLarge = TextStyle(fontWeight = FontWeight.Normal, fontSize = 16.sp, lineHeight = 24.sp),
    bodyMedium = TextStyle(fontWeight = FontWeight.Normal, fontSize = 14.sp, lineHeight = 20.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 14.sp, lineHeight = 20.sp),
    labelMedium = TextStyle(fontWeight = FontWeight.Medium, fontSize = 12.sp, lineHeight = 16.sp),
)
