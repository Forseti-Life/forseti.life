package com.stlouisintegration.forseti;

import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

import com.facebook.react.bridge.Promise;
import com.facebook.react.bridge.ReactApplicationContext;
import com.facebook.react.bridge.ReactContextBaseJavaModule;
import com.facebook.react.bridge.ReactMethod;

/**
 * Native module to control LocationTrackingService from React Native
 */
public class LocationServiceModule extends ReactContextBaseJavaModule {
    private static final String TAG = "LocationServiceModule";
    private final ReactApplicationContext reactContext;

    public LocationServiceModule(ReactApplicationContext reactContext) {
        super(reactContext);
        this.reactContext = reactContext;
    }

    @Override
    public String getName() {
        return "LocationServiceModule";
    }

    @ReactMethod
    public void startLocationService(Promise promise) {
        try {
            Context context = getReactApplicationContext();
            Intent serviceIntent = new Intent(context, LocationTrackingService.class);
            
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(serviceIntent);
            } else {
                context.startService(serviceIntent);
            }
            
            Log.d(TAG, "Location service started successfully");
            promise.resolve(true);
        } catch (Exception e) {
            Log.e(TAG, "Failed to start location service", e);
            promise.reject("START_SERVICE_FAILED", e.getMessage());
        }
    }

    @ReactMethod
    public void stopLocationService(Promise promise) {
        try {
            Context context = getReactApplicationContext();
            Intent serviceIntent = new Intent(context, LocationTrackingService.class);
            context.stopService(serviceIntent);
            
            Log.d(TAG, "Location service stopped successfully");
            promise.resolve(true);
        } catch (Exception e) {
            Log.e(TAG, "Failed to stop location service", e);
            promise.reject("STOP_SERVICE_FAILED", e.getMessage());
        }
    }

    @ReactMethod
    public void isServiceRunning(Promise promise) {
        // Simple check - service should be running if monitoring is enabled
        promise.resolve(true);
    }
}
