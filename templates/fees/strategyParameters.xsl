<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:xul="http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul"
	xmlns:xbl="http://www.mozilla.org/xbl"
	xmlns:php="http://php.net/xsl">
	<xsl:param name="IconsBase" />
	<xsl:param name="moduleName">order</xsl:param>
	<xsl:param name="panelName">BaseFeesApplicationStrategy</xsl:param>
	<xsl:param name="extendStrategySection" />
	<xsl:output indent="no" method="xml" omit-xml-declaration="yes" encoding="UTF-8" />
	
	<xsl:template match="/">
		<bindings xmlns="http://www.mozilla.org/xbl" xmlns:xbl="http://www.mozilla.org/xbl"
			xmlns:html="http://www.w3.org/1999/xhtml"
			xmlns:xul="http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul">
			<xsl:apply-templates select="sections" />
		</bindings>
	</xsl:template>
	
	<xsl:template match="sections">
			<binding xmlns="http://www.mozilla.org/xbl" id="cStrategy">
				<xsl:attribute name="extends"><xsl:value-of select="$extendStrategySection"/></xsl:attribute>
				<content>
					<xul:vbox flex="1">
						<xsl:apply-templates />
					</xul:vbox>
				</content>
				<implementation>
					<field name="mParameters"><xsl:value-of select="php:function('order_FeesService::XSLParameters')"/></field>
					<field name="mPanel">null</field>
					<constructor><![CDATA[
						wCore.debug('constructor cStrategy');
						var pNode = this.parentNode;
						while(pNode)
						{
							if ('mParameters' in pNode)
							{
								this.mPanel = pNode;
								this.setInitialValues(this.mPanel.mParameters);
								break;
							}
							pNode = pNode.parentNode;
						}
					]]></constructor>
			
					<destructor><![CDATA[
						wCore.debug('destructor cStrategy');
						this.mPanel = null;
					]]></destructor>						
				</implementation>			
			</binding>
	</xsl:template>
		
	<xsl:template match="section">
		<xul:cfieldsgroup >
			<xsl:attribute name="label"><xsl:value-of select="php:function('order_FeesService::XSLGetLabel', .)"/></xsl:attribute>
			<xsl:copy-of select="@class"/>
			<xsl:if test="@image">
				<xsl:attribute name="image"><xsl:value-of select="php:function('order_FeesService::XSLGetImage', .)"/></xsl:attribute>
			</xsl:if>
			<xsl:if test="@hidden">
				<xsl:attribute name="hide-content">true</xsl:attribute>
			</xsl:if>
			<xsl:apply-templates />
		</xul:cfieldsgroup>
	</xsl:template>
		
	<xsl:template match="field">
		<xul:row>
			<xsl:attribute name="anonid">row_<xsl:value-of select="@name" /></xsl:attribute>
			<xsl:variable name="elem" select="php:function('order_FeesService::XSLSetDefaultParInfo', .)" />
			<xsl:apply-templates select="$elem" mode="fieldLabel"/>
			<xsl:apply-templates select="$elem" mode="fieldInput"/>
		</xul:row>
	</xsl:template>
	
	<xsl:include href="field.xsl"/>
</xsl:stylesheet>