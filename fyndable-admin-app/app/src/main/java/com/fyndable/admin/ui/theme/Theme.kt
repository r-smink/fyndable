package com.fyndable.admin.ui.theme

import android.app.Activity
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

// Beautiful Claude-inspired color palette for Fyndable Ops
val FyndableNavy = Color(0xFF1E293B)       // Deep slate navy for headers
val FyndablePurple = Color(0xFF7C3AED)     // Vibrant purple for buttons
val FyndableBlue = Color(0xFF2563EB)       // Blue for gradient
val FyndableBackground = Color(0xFFF8FAFC) // Very soft light grey background
val FyndableSurface = Color(0xFFFFFFFF)    // Crisp white for cards

private val LightColors = lightColorScheme(
    primary = FyndablePurple,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFEEF2FF),
    onPrimaryContainer = Color(0xFF4338CA),
    secondary = FyndableNavy,
    onSecondary = Color.White,
    background = FyndableBackground,
    onBackground = Color(0xFF0F172A),
    surface = FyndableSurface,
    onSurface = Color(0xFF0F172A),
    surfaceVariant = Color(0xFFF1F5F9),
    onSurfaceVariant = Color(0xFF64748B),
    error = Color(0xFFEF4444),
    onError = Color.White,
)

private val DarkColors = darkColorScheme(
    primary = FyndablePurple,
    onPrimary = Color.White,
    primaryContainer = Color(0xFF312E81),
    onPrimaryContainer = Color(0xFFE0E7FF),
    secondary = FyndableNavy,
    onSecondary = Color.White,
    background = Color(0xFF0F172A),
    onBackground = Color(0xFFF8FAFC),
    surface = Color(0xFF1E293B),
    onSurface = Color(0xFFF8FAFC),
    surfaceVariant = Color(0xFF334155),
    onSurfaceVariant = Color(0xFF94A3B8),
    error = Color(0xFFF87171),
    onError = Color.White,
)

@Composable
fun FyndableAdminTheme(
    content: @Composable () -> Unit,
) {
    // The UI uses hardcoded light colors throughout (white cards, light grey
    // backgrounds). Dark theme would make text invisible. Force light mode.
    val colorScheme = LightColors

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = Color.Transparent.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = true
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
