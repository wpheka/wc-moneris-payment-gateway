const monerisPopulateBrowserParams = {
	initParameters: function (methodName) {
		let java_enabled;
		try {
			java_enabled = navigator.javaEnabled();
		} catch (e) {
			java_enabled = false;
		}

		this.fieldNames = {
			[`${methodName}_java_enabled`]: java_enabled,
			[`${methodName}_color_depth`]: screen.colorDepth.toString(),
			[`${methodName}_browser_language`]: navigator.language,
			[`${methodName}_screen_height`]: screen.height.toString(),
			[`${methodName}_screen_width`]: screen.width.toString(),
			[`${methodName}_user_agent`]: navigator.userAgent,
			[`${methodName}_browser_timezone_zone_offset`]: (new Date()).getTimezoneOffset().toString()
		};
	},
	execute: function (methodName) {
		this.initParameters(methodName);
		return this.fieldNames;
	}
};

export default monerisPopulateBrowserParams;